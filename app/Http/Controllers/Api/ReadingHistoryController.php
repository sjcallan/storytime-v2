<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\ReadingHistory;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReadingHistoryController extends Controller
{
    use AuthorizesRequests;

    /**
     * Get the profile ID for reading history.
     * Falls back to the book's profile, then the user's default profile.
     */
    private function getProfileId(Book $book): string
    {
        $profileId = session('current_profile_id');

        if ($profileId) {
            return $profileId;
        }

        // Fall back to the book's profile
        if ($book->profile_id) {
            return $book->profile_id;
        }

        // Fall back to user's default profile
        /** @var User $user */
        $user = auth()->user();
        $defaultProfile = $user->defaultProfile();

        if ($defaultProfile) {
            return $defaultProfile->id;
        }

        // Create default profile if none exists
        return $user->createDefaultProfile()->id;
    }

    /**
     * Get or create reading history when a book is opened.
     * If history exists, updates last_read_at timestamp.
     * Returns the reading history so frontend knows which chapter to resume.
     */
    public function open(Request $request, Book $book): JsonResponse
    {
        $this->authorize('view', $book);

        $userId = auth()->id();
        $profileId = $this->getProfileId($book);

        $readingHistory = ReadingHistory::updateOrCreate(
            [
                'user_id' => $userId,
                'book_id' => $book->id,
                'profile_id' => $profileId,
            ],
            [
                'last_read_at' => now(),
            ]
        );

        // Set default chapter number if this is a new record
        if (! $readingHistory->current_chapter_number) {
            $readingHistory->update(['current_chapter_number' => 1]);
        }

        $readingHistory->load(['book.coverImage', 'chapter']);

        return response()->json($readingHistory);
    }

    /**
     * Update the current chapter number when user finishes reading a chapter.
     * Called when user reaches the end of a chapter.
     */
    public function advanceChapter(Request $request, Book $book): JsonResponse
    {
        $this->authorize('view', $book);

        $validated = $request->validate([
            'chapter_number' => ['required', 'integer', 'min:1'],
            'chapter_id' => ['nullable', 'string', 'exists:chapters,id'],
        ]);

        $userId = auth()->id();
        $profileId = $this->getProfileId($book);

        $readingHistory = ReadingHistory::updateOrCreate(
            [
                'user_id' => $userId,
                'book_id' => $book->id,
                'profile_id' => $profileId,
            ],
            [
                'current_chapter_number' => $validated['chapter_number'],
                'chapter_id' => $validated['chapter_id'] ?? null,
                'last_read_at' => now(),
            ]
        );

        $readingHistory->load(['book.coverImage', 'chapter']);

        return response()->json($readingHistory);
    }
}
