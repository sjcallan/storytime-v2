<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileImageRequest;
use App\Http\Requests\Settings\StoreProfileRequest;
use App\Http\Requests\Settings\UpdateProfileRequest;
use App\Models\Profile;
use App\Services\Profile\ProfileImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProfilesController extends Controller
{
    public function __construct(
        protected ProfileImageService $profileImageService
    ) {}

    /**
     * Display the profile selection page.
     */
    public function select(Request $request): Response
    {
        $profiles = $request->user()
            ->profiles()
            ->orderByDesc('is_default')
            ->orderBy('created_at')
            ->get();

        return Inertia::render('ProfileSelect', [
            'profiles' => $profiles,
            'currentProfileId' => $request->session()->get('current_profile_id'),
            'ageGroups' => Profile::AGE_GROUPS,
        ]);
    }

    /**
     * Display the profiles management page.
     */
    public function index(Request $request): Response
    {
        $profiles = $request->user()
            ->profiles()
            ->orderByDesc('is_default')
            ->orderBy('created_at')
            ->get();

        return Inertia::render('settings/Profiles', [
            'profiles' => $profiles,
            'ageGroups' => Profile::AGE_GROUPS,
        ]);
    }

    /**
     * Store a new profile.
     */
    public function store(StoreProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $user->profiles()->create($validated);

        return back()->with('status', 'profile-created');
    }

    /**
     * Update a profile.
     */
    public function update(UpdateProfileRequest $request, Profile $profile): RedirectResponse
    {
        $profile->update($request->validated());

        return back()->with('status', 'profile-updated');
    }

    /**
     * Delete a profile.
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

        return back()->with('status', 'profile-deleted');
    }

    /**
     * Set a profile as default.
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
     * Update a profile's image.
     */
    public function updateImage(ProfileImageRequest $request, Profile $profile): RedirectResponse
    {
        if ($profile->user_id !== $request->user()->id) {
            abort(403);
        }

        if ($profile->profile_image_path) {
            Storage::disk('public')->delete($profile->profile_image_path);
        }

        $file = $request->file('image');
        $resizedImage = $this->resizeImage($file->getRealPath(), $file->getMimeType());

        $path = 'profile-images/'.$profile->id.'-'.time().'.jpg';
        Storage::disk('public')->put($path, $resizedImage);

        $profile->forceFill(['profile_image_path' => $path])->save();

        return back()->with('status', 'profile-image-updated');
    }

    /**
     * Delete a profile's image.
     */
    public function destroyImage(Request $request, Profile $profile): RedirectResponse
    {
        if ($profile->user_id !== $request->user()->id) {
            abort(403);
        }

        $profile->deleteProfileImage();

        return back()->with('status', 'profile-image-deleted');
    }

    /**
     * Switch to a different profile (stored in session).
     */
    public function switchProfile(Request $request, Profile $profile): JsonResponse|RedirectResponse
    {
        $user = $request->user();

        if ($profile->user_id !== $user->id) {
            abort(403);
        }

        $previousProfileId = $request->session()->get('current_profile_id');
        $request->session()->put('current_profile_id', $profile->id);

        if ($previousProfileId !== $profile->id) {
            $request->session()->forget('pin_verification');
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'profile' => $profile,
            ]);
        }

        return back()->with('status', 'profile-switched');
    }

    /**
     * Generate an AI profile image.
     */
    public function generateImage(Request $request, Profile $profile): JsonResponse
    {
        if ($profile->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'description' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        $result = $this->profileImageService->generateProfileImage($validated['description']);

        if ($result['error'] !== null) {
            return response()->json([
                'success' => false,
                'error' => $result['error'],
            ], 422);
        }

        if ($profile->profile_image_path) {
            Storage::disk('public')->delete($profile->profile_image_path);
        }

        $relativePath = str_replace(Storage::disk('public')->url(''), '', $result['url']);
        $profile->forceFill([
            'profile_image_path' => $relativePath,
            'profile_image_prompt' => $validated['description'],
        ])->save();

        return response()->json([
            'success' => true,
            'avatar' => $result['url'],
        ]);
    }

    /**
     * Resize the image to 256x256 using GD library.
     */
    private function resizeImage(string $sourcePath, string $mimeType): string
    {
        $size = 256;

        $sourceImage = match ($mimeType) {
            'image/jpeg', 'image/jpg' => imagecreatefromjpeg($sourcePath),
            'image/png' => imagecreatefrompng($sourcePath),
            'image/gif' => imagecreatefromgif($sourcePath),
            'image/webp' => imagecreatefromwebp($sourcePath),
            default => imagecreatefromjpeg($sourcePath),
        };

        $sourceWidth = imagesx($sourceImage);
        $sourceHeight = imagesy($sourceImage);

        $cropSize = min($sourceWidth, $sourceHeight);
        $cropX = (int) (($sourceWidth - $cropSize) / 2);
        $cropY = (int) (($sourceHeight - $cropSize) / 2);

        $croppedImage = imagecreatetruecolor($cropSize, $cropSize);
        imagecopy($croppedImage, $sourceImage, 0, 0, $cropX, $cropY, $cropSize, $cropSize);

        $resizedImage = imagecreatetruecolor($size, $size);
        imagecopyresampled($resizedImage, $croppedImage, 0, 0, 0, 0, $size, $size, $cropSize, $cropSize);

        ob_start();
        imagejpeg($resizedImage, null, 90);
        $imageData = ob_get_clean();

        imagedestroy($sourceImage);
        imagedestroy($croppedImage);
        imagedestroy($resizedImage);

        return $imageData;
    }
}
