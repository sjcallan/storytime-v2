<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class PinController extends Controller
{
    /**
     * Store or update the user's PIN.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'pin' => ['required', 'string', 'size:4', 'regex:/^\d{4}$/'],
            'pin_confirmation' => ['required', 'string', 'same:pin'],
        ], [
            'pin.required' => 'Please enter a PIN.',
            'pin.size' => 'PIN must be exactly 4 digits.',
            'pin.regex' => 'PIN must contain only numbers.',
            'pin_confirmation.same' => 'PIN confirmation does not match.',
        ]);

        $request->user()->update([
            'pin' => $validated['pin'],
        ]);

        return back();
    }

    /**
     * Remove the user's PIN.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->user()->update([
            'pin' => null,
        ]);

        $request->session()->forget('pin_verification');

        return back();
    }

    /**
     * Verify the user's PIN.
     */
    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pin' => ['required', 'string', 'size:4'],
        ]);

        $user = $request->user();

        if (! $user->hasPin()) {
            return response()->json([
                'verified' => true,
                'message' => 'No PIN is set.',
            ]);
        }

        if (Hash::check($validated['pin'], $user->pin)) {
            $currentProfileId = $request->session()->get('current_profile_id');

            $request->session()->put('pin_verification', [
                'verified_until' => now()->addMinutes(30),
                'profile_id' => $currentProfileId,
            ]);

            return response()->json([
                'verified' => true,
                'message' => 'PIN verified successfully.',
            ]);
        }

        return response()->json([
            'verified' => false,
            'message' => 'Invalid PIN.',
        ], 422);
    }

    /**
     * Check if PIN verification is required.
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasPin()) {
            return response()->json([
                'has_pin' => false,
                'requires_verification' => false,
            ]);
        }

        $currentProfileId = $request->session()->get('current_profile_id');
        $pinVerification = $request->session()->get('pin_verification');

        $isVerified = $pinVerification
            && isset($pinVerification['verified_until'])
            && isset($pinVerification['profile_id'])
            && $pinVerification['profile_id'] === $currentProfileId
            && now()->lt($pinVerification['verified_until']);

        return response()->json([
            'has_pin' => true,
            'requires_verification' => ! $isVerified,
        ]);
    }
}
