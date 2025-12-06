<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        $user = $request->user();
        $profiles = [];
        $currentProfile = null;

        if ($user) {
            $profiles = $user->profiles()
                ->orderByDesc('is_default')
                ->orderBy('created_at')
                ->get();

            $currentProfileId = $request->session()->get('current_profile_id');

            if ($currentProfileId) {
                $currentProfile = $profiles->firstWhere('id', $currentProfileId);
            }

            if (! $currentProfile) {
                $currentProfile = $profiles->firstWhere('is_default', true) ?? $profiles->first();

                if ($currentProfile) {
                    $request->session()->put('current_profile_id', $currentProfile->id);
                }
            }
        }

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $user,
                'profiles' => $profiles,
                'currentProfile' => $currentProfile,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'config' => [
                'storytime' => [
                    'adult_genres_enabled' => config('storytime.adult_genres_enabled'),
                ],
            ],
        ];
    }
}
