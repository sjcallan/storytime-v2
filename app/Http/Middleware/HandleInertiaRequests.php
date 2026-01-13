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

        $shared = [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $user,
                'profiles' => $profiles,
                'currentProfile' => $currentProfile,
                'hasPin' => $user?->hasPin() ?? false,
                'isAdmin' => (bool) ($user?->is_admin ?? false),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'config' => [
                'storytime' => [
                    'adult_genres_enabled' => config('storytime.adult_genres_enabled'),
                ],
            ],
        ];

        if (app()->environment('local')) {
            $defaultProvider = config('ai.default');
            $providerConfig = config("ai.providers.{$defaultProvider}", []);

            $shared['aiDebug'] = [
                'environment' => app()->environment(),
                'default_provider' => $defaultProvider,
                'active_provider' => [
                    'driver' => $providerConfig['driver'] ?? 'unknown',
                    'model' => $providerConfig['model'] ?? 'unknown',
                    'max_tokens' => $providerConfig['max_tokens'] ?? 0,
                    'temperature' => $providerConfig['temperature'] ?? 0,
                    'base_url' => $providerConfig['base_url'] ?? null,
                ],
                'moderation' => [
                    'enabled' => config('ai.moderation.enabled', false),
                    'model' => config('ai.moderation.model', 'unknown'),
                    'min_threshold' => config('ai.moderation.min_threshold', 0.5),
                ],
                'image_generation' => [
                    'provider' => 'replicate',
                    'models' => array_keys(config('ai.image_generation.replicate', [])),
                    'use_custom_model' => config('services.replicate.use_custom_model', false),
                    'custom_model_version' => config('services.replicate.custom_model_version'),
                    'custom_model_lora' => config('services.replicate.custom_model_lora'),
                    'custom_model_lora_scale' => config('services.replicate.custom_model_lora_scale'),
                ],
            ];
        }

        return $shared;
    }
}
