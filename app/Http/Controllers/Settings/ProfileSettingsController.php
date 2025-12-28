<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateModerationThresholdsRequest;
use App\Http\Requests\Settings\UpdateProfileSettingsRequest;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Profile;
use App\Models\RequestLog;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProfileSettingsController extends Controller
{
    /**
     * Display the profile settings page.
     */
    public function show(Request $request, Profile $profile): Response
    {
        if ($profile->user_id !== $request->user()->id) {
            abort(403);
        }

        $startDate = $request->input('start_date')
            ? Carbon::parse($request->input('start_date'))->startOfDay()
            : Carbon::now()->subDays(30)->startOfDay();

        $endDate = $request->input('end_date')
            ? Carbon::parse($request->input('end_date'))->endOfDay()
            : Carbon::now()->endOfDay();

        $dateRangeQuery = RequestLog::query()
            ->where('profile_id', $profile->id)
            ->whereBetween('created_at', [$startDate, $endDate]);

        $totalStats = (clone $dateRangeQuery)->selectRaw('
            SUM(total_cost) as total_cost,
            SUM(total_tokens) as total_tokens,
            SUM(prompt_tokens) as prompt_tokens,
            SUM(completion_tokens) as completion_tokens,
            SUM(output_images_count) as output_images,
            COUNT(*) as total_requests
        ')->first();

        $dailyCosts = RequestLog::query()
            ->where('profile_id', $profile->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('DATE(created_at) as date, SUM(total_cost) as cost')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'date' => $row->date,
                'cost' => (float) $row->cost,
            ]);

        $allDates = [];
        $current = $startDate->copy();
        while ($current <= $endDate) {
            $allDates[$current->format('Y-m-d')] = 0;
            $current->addDay();
        }

        foreach ($dailyCosts as $day) {
            $allDates[$day['date']] = $day['cost'];
        }

        $chartData = collect($allDates)->map(fn ($cost, $date) => [
            'date' => $date,
            'cost' => $cost,
        ])->values();

        $profileBooks = Book::query()
            ->where('profile_id', $profile->id)
            ->count();

        $profileChapters = Chapter::query()
            ->where('profile_id', $profile->id)
            ->count();

        $allTimeStats = RequestLog::query()
            ->where('profile_id', $profile->id)
            ->selectRaw('
                SUM(total_cost) as total_cost,
                SUM(total_tokens) as total_tokens,
                COUNT(*) as total_requests
            ')->first();

        return Inertia::render('settings/ProfileSettings', [
            'profile' => $profile,
            'ageGroups' => Profile::AGE_GROUPS,
            'moderationCategories' => Profile::MODERATION_CATEGORIES,
            'defaultThresholds' => $profile->getDefaultModerationThresholds(),
            'stats' => [
                'total_cost' => (float) ($totalStats->total_cost ?? 0),
                'total_tokens' => (int) ($totalStats->total_tokens ?? 0),
                'prompt_tokens' => (int) ($totalStats->prompt_tokens ?? 0),
                'completion_tokens' => (int) ($totalStats->completion_tokens ?? 0),
                'output_images' => (int) ($totalStats->output_images ?? 0),
                'total_requests' => (int) ($totalStats->total_requests ?? 0),
                'total_books' => $profileBooks,
                'total_chapters' => $profileChapters,
            ],
            'allTimeStats' => [
                'total_cost' => (float) ($allTimeStats->total_cost ?? 0),
                'total_tokens' => (int) ($allTimeStats->total_tokens ?? 0),
                'total_requests' => (int) ($allTimeStats->total_requests ?? 0),
                'total_books' => $profileBooks,
                'total_chapters' => $profileChapters,
            ],
            'chartData' => $chartData,
            'filters' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
            ],
        ]);
    }

    /**
     * Update the profile's basic settings.
     */
    public function update(UpdateProfileSettingsRequest $request, Profile $profile): RedirectResponse
    {
        if ($profile->user_id !== $request->user()->id) {
            abort(403);
        }

        $profile->update($request->validated());

        return back()->with('status', 'profile-updated');
    }

    /**
     * Update the profile's moderation thresholds.
     */
    public function updateModeration(UpdateModerationThresholdsRequest $request, Profile $profile): RedirectResponse
    {
        if ($profile->user_id !== $request->user()->id) {
            abort(403);
        }

        $thresholds = $request->validated()['thresholds'];

        $profile->update(['moderation_thresholds' => $thresholds]);

        return back()->with('status', 'moderation-updated');
    }

    /**
     * Reset moderation thresholds to age-based defaults.
     */
    public function resetModeration(Request $request, Profile $profile): RedirectResponse
    {
        if ($profile->user_id !== $request->user()->id) {
            abort(403);
        }

        $profile->update(['moderation_thresholds' => null]);

        return back()->with('status', 'moderation-reset');
    }

    /**
     * Set this profile as default.
     */
    public function setDefault(Request $request, Profile $profile): RedirectResponse
    {
        $user = $request->user();

        if ($profile->user_id !== $user->id) {
            abort(403);
        }

        $user->profiles()->update(['is_default' => false]);
        $profile->update(['is_default' => true]);

        return back()->with('status', 'default-profile-set');
    }

    /**
     * Delete the profile.
     */
    public function destroy(Request $request, Profile $profile): RedirectResponse
    {
        $user = $request->user();

        if ($profile->user_id !== $user->id) {
            abort(403);
        }

        if ($profile->is_default) {
            return back()->withErrors(['profile' => 'Cannot delete the default profile.']);
        }

        if ($profile->profile_image_path) {
            Storage::disk('public')->delete($profile->profile_image_path);
        }

        $profile->delete();

        return redirect()->route('profiles.index')->with('status', 'profile-deleted');
    }
}
