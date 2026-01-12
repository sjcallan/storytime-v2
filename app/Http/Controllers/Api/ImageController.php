<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\Image\GenerateImageJob;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Character;
use App\Models\Image;
use App\Services\Image\ImageService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImageController extends Controller
{
    use AuthorizesRequests;

    public function __construct(protected ImageService $imageService) {}

    /**
     * Display a listing of images (filtered by book).
     */
    public function index(Request $request): JsonResponse
    {
        $bookId = $request->query('book_id');

        if ($bookId) {
            $book = Book::findOrFail($bookId);
            $this->authorize('view', $book);

            $images = $this->imageService->getAllByBookId($bookId);
        } else {
            $images = Image::query()
                ->where('user_id', auth()->id())
                ->orderBy('created_at', 'desc')
                ->limit(100)
                ->get();
        }

        return response()->json($images);
    }

    /**
     * Display the specified image.
     */
    public function show(Image $image): JsonResponse
    {
        if ($image->book_id) {
            $this->authorize('view', $image->book);
        }

        return response()->json([
            'id' => $image->id,
            'book_id' => $image->book_id,
            'chapter_id' => $image->chapter_id,
            'character_id' => $image->character_id,
            'type' => $image->type->value,
            'image_url' => $image->image_url,
            'full_url' => $image->full_url,
            'prompt' => $image->prompt,
            'error' => $image->error,
            'status' => $image->status->value,
            'paragraph_index' => $image->paragraph_index,
            'aspect_ratio' => $image->aspect_ratio,
            'is_ready' => $image->is_ready,
            'is_processing' => $image->is_processing,
            'has_error' => $image->has_error,
            'created_at' => $image->created_at,
            'updated_at' => $image->updated_at,
        ]);
    }

    /**
     * Remove the specified image.
     */
    public function destroy(Image $image): JsonResponse
    {
        if ($image->book_id) {
            $this->authorize('update', $image->book);
        }

        $image->delete();

        return response()->json(null, 204);
    }

    /**
     * Regenerate an existing image by creating a new Image record.
     */
    public function regenerate(Image $image): JsonResponse
    {
        if ($image->book_id) {
            $this->authorize('update', $image->book);
        }

        // Create a NEW image record based on the existing one
        $newImage = $this->imageService->createFromExisting($image);

        // Update the foreign key on the associated entity
        $this->imageService->updateEntityImageReference($newImage);

        // Dispatch the generation job
        GenerateImageJob::dispatch($newImage)->onQueue('images');

        return response()->json([
            'message' => 'Image regeneration started',
            'image' => [
                'id' => $newImage->id,
                'status' => $newImage->status->value,
                'type' => $newImage->type->value,
            ],
        ]);
    }

    /**
     * Create and generate a book cover image.
     * Always creates a new Image record to preserve history.
     */
    public function createBookCover(Request $request, Book $book): JsonResponse
    {
        $this->authorize('update', $book);

        $prompt = $request->input('prompt');

        // Check for existing cover image to copy prompt from
        $existingImage = $this->imageService->getBookCover($book->id);

        // Always create a new image record
        $image = $this->imageService->createBookCoverImage(
            $book,
            $prompt ?: $existingImage?->prompt
        );

        // Update the book's cover_image_id to point to new image
        $book->update(['cover_image_id' => $image->id]);

        GenerateImageJob::dispatch($image)->onQueue('images');

        return response()->json([
            'message' => 'Book cover generation started',
            'image' => [
                'id' => $image->id,
                'status' => $image->status->value,
                'type' => $image->type->value,
            ],
        ], 201);
    }

    /**
     * Create and generate a chapter header image.
     * Always creates a new Image record to preserve history.
     */
    public function createChapterHeader(Request $request, Chapter $chapter): JsonResponse
    {
        $this->authorize('update', $chapter->book);

        // Check for existing header image to copy prompt from
        $existingImage = $this->imageService->getChapterHeader($chapter->id);

        // Determine prompt: request > existing image > chapter's image_prompt
        $prompt = $request->input('prompt') ?: $existingImage?->prompt ?: $chapter->image_prompt;

        // Always create a new image record
        $image = $this->imageService->createChapterHeaderImage($chapter, $prompt);

        // Update chapter's header_image_id to point to new image
        $chapter->update(['header_image_id' => $image->id]);

        GenerateImageJob::dispatch($image)->onQueue('images');

        return response()->json([
            'message' => 'Chapter header image generation started',
            'image' => [
                'id' => $image->id,
                'status' => $image->status->value,
                'type' => $image->type->value,
            ],
        ], 201);
    }

    /**
     * Create and generate a chapter inline image.
     * Always creates a new Image record to preserve history.
     */
    public function createChapterInline(Request $request, Chapter $chapter): JsonResponse
    {
        $this->authorize('update', $chapter->book);

        $paragraphIndex = $request->input('paragraph_index');
        $prompt = $request->input('prompt');

        if ($paragraphIndex === null) {
            return response()->json(['message' => 'paragraph_index is required'], 422);
        }

        // Check for existing inline image at this paragraph to copy prompt from
        $existingImage = $this->imageService->getInlineImageByParagraphIndex($chapter->id, $paragraphIndex);

        // Determine prompt: request > existing image
        $finalPrompt = $prompt ?: $existingImage?->prompt;

        if (! $finalPrompt) {
            return response()->json(['message' => 'prompt is required for new inline images'], 422);
        }

        // Always create a new inline image record
        $image = $this->imageService->createChapterInlineImage($chapter, $paragraphIndex, $finalPrompt);

        GenerateImageJob::dispatch($image)->onQueue('images');

        return response()->json([
            'message' => 'Chapter inline image generation started',
            'image' => [
                'id' => $image->id,
                'status' => $image->status->value,
                'type' => $image->type->value,
                'paragraph_index' => $image->paragraph_index,
            ],
        ], 201);
    }

    /**
     * Create and generate a character portrait image.
     * Always creates a new Image record to preserve history.
     */
    public function createCharacterPortrait(Request $request, Character $character): JsonResponse
    {
        $this->authorize('update', $character->book);

        $prompt = $request->input('prompt');

        // Check for existing portrait image to copy prompt from
        $existingImage = $this->imageService->getCharacterPortrait($character->id);

        // Always create a new image record
        $image = $this->imageService->createCharacterPortraitImage(
            $character,
            $prompt ?: $existingImage?->prompt
        );

        // Update character's portrait_image_id to point to new image
        $character->update(['portrait_image_id' => $image->id]);

        GenerateImageJob::dispatch($image)->onQueue('images');

        return response()->json([
            'message' => 'Character portrait generation started',
            'image' => [
                'id' => $image->id,
                'status' => $image->status->value,
                'type' => $image->type->value,
            ],
        ], 201);
    }

    /**
     * Cancel an image generation (mark as cancelled).
     */
    public function cancel(Image $image): JsonResponse
    {
        if ($image->book_id) {
            $this->authorize('update', $image->book);
        }

        $image = $this->imageService->markCancelled($image);

        return response()->json([
            'message' => 'Image cancelled',
            'image' => [
                'id' => $image->id,
                'status' => $image->status->value,
            ],
        ]);
    }

    /**
     * Update an image's prompt.
     */
    public function updatePrompt(Request $request, Image $image): JsonResponse
    {
        if ($image->book_id) {
            $this->authorize('update', $image->book);
        }

        $prompt = $request->input('prompt');

        if (! $prompt) {
            return response()->json(['message' => 'prompt is required'], 422);
        }

        $image = $this->imageService->updateById($image->id, ['prompt' => $prompt]);

        return response()->json([
            'message' => 'Prompt updated',
            'image' => [
                'id' => $image->id,
                'prompt' => $image->prompt,
            ],
        ]);
    }
}
