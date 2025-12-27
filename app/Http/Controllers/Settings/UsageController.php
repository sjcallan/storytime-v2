<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\RequestLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UsageController extends Controller
{
    /**
     * Display the usage statistics page.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $profileId = $request->input('profile_id');
        $bookId = $request->input('book_id');

        $query = RequestLog::query()
            ->where('user_id', $user->id)
            ->with(['profile:id,name', 'book:id,title'])
            ->orderByDesc('created_at');

        if ($profileId) {
            $query->where('profile_id', $profileId);
        }

        if ($bookId) {
            $query->where('book_id', $bookId);
        }

        $logs = $query->paginate(15)->withQueryString();

        $statsQuery = RequestLog::query()
            ->where('user_id', $user->id);

        if ($profileId) {
            $statsQuery->where('profile_id', $profileId);
        }

        if ($bookId) {
            $statsQuery->where('book_id', $bookId);
        }

        $allTimeStats = (clone $statsQuery)->selectRaw('
            SUM(prompt_tokens) as total_prompt_tokens,
            SUM(completion_tokens) as total_completion_tokens,
            SUM(total_tokens) as total_tokens,
            SUM(total_cost) as total_cost,
            COUNT(*) as total_requests
        ')->first();

        $thirtyDaysAgo = Carbon::now()->subDays(30);
        $last30DaysStats = (clone $statsQuery)
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->selectRaw('
                SUM(prompt_tokens) as total_prompt_tokens,
                SUM(completion_tokens) as total_completion_tokens,
                SUM(total_tokens) as total_tokens,
                SUM(total_cost) as total_cost,
                COUNT(*) as total_requests
            ')->first();

        $books = Book::query()
            ->where('user_id', $user->id)
            ->select('id', 'title')
            ->orderBy('title')
            ->get();

        return Inertia::render('settings/Usage', [
            'logs' => $logs,
            'books' => $books,
            'allTimeStats' => [
                'prompt_tokens' => (int) ($allTimeStats->total_prompt_tokens ?? 0),
                'completion_tokens' => (int) ($allTimeStats->total_completion_tokens ?? 0),
                'total_tokens' => (int) ($allTimeStats->total_tokens ?? 0),
                'total_cost' => (float) ($allTimeStats->total_cost ?? 0),
                'total_requests' => (int) ($allTimeStats->total_requests ?? 0),
            ],
            'last30DaysStats' => [
                'prompt_tokens' => (int) ($last30DaysStats->total_prompt_tokens ?? 0),
                'completion_tokens' => (int) ($last30DaysStats->total_completion_tokens ?? 0),
                'total_tokens' => (int) ($last30DaysStats->total_tokens ?? 0),
                'total_cost' => (float) ($last30DaysStats->total_cost ?? 0),
                'total_requests' => (int) ($last30DaysStats->total_requests ?? 0),
            ],
            'selectedProfileId' => $profileId,
            'selectedBookId' => $bookId,
        ]);
    }
}
