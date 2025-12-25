<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Profile;
use App\Services\Theme\BackgroundImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ThemeController extends Controller
{
    public function __construct(
        protected BackgroundImageService $backgroundImageService
    ) {}

    /**
     * Store a new theme for the current profile.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'background_color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'text_color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'background_image' => ['nullable', 'string', 'max:500'],
            'background_description' => ['nullable', 'string', 'max:500'],
        ]);

        $profile = $this->getCurrentProfile($request);

        if (! $profile) {
            return response()->json(['error' => 'No profile selected'], 400);
        }

        $theme = [
            'id' => (string) Str::ulid(),
            'name' => $validated['name'],
            'background_color' => $validated['background_color'],
            'text_color' => $validated['text_color'],
            'background_image' => $validated['background_image'] ?? null,
            'background_description' => $validated['background_description'] ?? null,
        ];

        $profile->addTheme($theme);

        return response()->json([
            'success' => true,
            'theme' => $theme,
            'themes' => $profile->fresh()->themes,
        ]);
    }

    /**
     * Update an existing theme.
     */
    public function update(Request $request, string $themeId): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'background_color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'text_color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'background_image' => ['nullable', 'string', 'max:500'],
            'background_description' => ['nullable', 'string', 'max:500'],
        ]);

        $profile = $this->getCurrentProfile($request);

        if (! $profile) {
            return response()->json(['error' => 'No profile selected'], 400);
        }

        $theme = [
            'id' => $themeId,
            'name' => $validated['name'],
            'background_color' => $validated['background_color'],
            'text_color' => $validated['text_color'],
            'background_image' => $validated['background_image'] ?? null,
            'background_description' => $validated['background_description'] ?? null,
        ];

        $profile->updateTheme($theme);

        return response()->json([
            'success' => true,
            'theme' => $theme,
            'themes' => $profile->fresh()->themes,
        ]);
    }

    /**
     * Delete a theme.
     */
    public function destroy(Request $request, string $themeId): JsonResponse
    {
        $profile = $this->getCurrentProfile($request);

        if (! $profile) {
            return response()->json(['error' => 'No profile selected'], 400);
        }

        $profile->deleteTheme($themeId);

        return response()->json([
            'success' => true,
            'themes' => $profile->fresh()->themes,
            'active_theme_id' => $profile->fresh()->active_theme_id,
        ]);
    }

    /**
     * Set the active theme for the current profile.
     */
    public function setActive(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'theme_id' => ['nullable', 'string'],
        ]);

        $profile = $this->getCurrentProfile($request);

        if (! $profile) {
            return response()->json(['error' => 'No profile selected'], 400);
        }

        $profile->setActiveTheme($validated['theme_id']);

        return response()->json([
            'success' => true,
            'active_theme_id' => $profile->fresh()->active_theme_id,
            'active_theme' => $profile->fresh()->active_theme,
        ]);
    }

    /**
     * Generate a background image using AI.
     */
    public function generateBackgroundImage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'description' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        $profile = $this->getCurrentProfile($request);

        if (! $profile) {
            return response()->json(['error' => 'No profile selected'], 400);
        }

        $result = $this->backgroundImageService->generateBackgroundImage(
            $validated['description'],
            $request->user()?->id,
            $profile->id
        );

        if ($result['error'] !== null) {
            return response()->json([
                'success' => false,
                'error' => $result['error'],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'background_image' => $result['url'],
        ]);
    }

    /**
     * Update the background image for the current profile's active theme.
     */
    public function setBackgroundImage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'background_image' => ['nullable', 'string', 'max:500'],
        ]);

        $profile = $this->getCurrentProfile($request);

        if (! $profile) {
            return response()->json(['error' => 'No profile selected'], 400);
        }

        $profile->background_image = $validated['background_image'];
        $profile->save();

        return response()->json([
            'success' => true,
            'background_image' => $profile->background_image,
        ]);
    }

    /**
     * Get the current profile from the session.
     */
    private function getCurrentProfile(Request $request): ?Profile
    {
        $profileId = $request->session()->get('current_profile_id');

        if (! $profileId) {
            $profile = $request->user()->defaultProfile();
            if ($profile) {
                $request->session()->put('current_profile_id', $profile->id);

                return $profile;
            }

            return null;
        }

        return Profile::query()
            ->where('id', $profileId)
            ->where('user_id', $request->user()->id)
            ->first();
    }
}
