<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\Favorite;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    use AuthorizesRequests;

    /**
     * Get the profile ID for favorites.
     * Falls back to the book's profile, then the user's default profile.
     */
    private function getProfileId(Book $book): string
    {
        $profileId = session('current_profile_id');

        if ($profileId) {
            return $profileId;
        }

        if ($book->profile_id) {
            return $book->profile_id;
        }

        /** @var User $user */
        $user = auth()->user();
        $defaultProfile = $user->defaultProfile();

        if ($defaultProfile) {
            return $defaultProfile->id;
        }

        return $user->createDefaultProfile()->id;
    }

    /**
     * Check if a book is favorited by the current user/profile.
     */
    public function check(Book $book): JsonResponse
    {
        $this->authorize('view', $book);

        $userId = auth()->id();
        $profileId = $this->getProfileId($book);

        $isFavorite = Favorite::query()
            ->where('user_id', $userId)
            ->where('profile_id', $profileId)
            ->where('book_id', $book->id)
            ->exists();

        return response()->json([
            'is_favorite' => $isFavorite,
        ]);
    }

    /**
     * Add a book to favorites.
     */
    public function store(Request $request, Book $book): JsonResponse
    {
        $this->authorize('view', $book);

        $userId = auth()->id();
        $profileId = $this->getProfileId($book);

        $favorite = Favorite::firstOrCreate([
            'user_id' => $userId,
            'profile_id' => $profileId,
            'book_id' => $book->id,
        ]);

        $favorite->load('book');

        return response()->json([
            'favorite' => $favorite,
            'is_favorite' => true,
        ], 201);
    }

    /**
     * Remove a book from favorites.
     */
    public function destroy(Request $request, Book $book): JsonResponse
    {
        $this->authorize('view', $book);

        $userId = auth()->id();
        $profileId = $this->getProfileId($book);

        Favorite::query()
            ->where('user_id', $userId)
            ->where('profile_id', $profileId)
            ->where('book_id', $book->id)
            ->delete();

        return response()->json([
            'is_favorite' => false,
        ]);
    }
}
