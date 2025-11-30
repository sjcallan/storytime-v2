<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfilePhotoRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return to_route('profile.edit');
    }

    /**
     * Update the user's profile photo.
     */
    public function updatePhoto(ProfilePhotoRequest $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->profile_photo_path) {
            Storage::disk('public')->delete($user->profile_photo_path);
        }

        $file = $request->file('photo');
        $resizedImage = $this->resizeImage($file->getRealPath(), $file->getMimeType());

        $path = 'profile-photos/'.$user->id.'-'.time().'.jpg';
        Storage::disk('public')->put($path, $resizedImage);

        $user->forceFill(['profile_photo_path' => $path])->save();

        return back()->with('status', 'profile-photo-updated');
    }

    /**
     * Delete the user's profile photo.
     */
    public function destroyPhoto(Request $request): RedirectResponse
    {
        $request->user()->deleteProfilePhoto();

        return back()->with('status', 'profile-photo-deleted');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        if ($user->profile_photo_path) {
            Storage::disk('public')->delete($user->profile_photo_path);
        }

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
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
