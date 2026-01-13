<?php

namespace App\Http\Controllers\Api;

use App\Enums\ImageStatus;
use App\Enums\ImageType;
use App\Http\Controllers\Controller;
use App\Http\Requests\EditImageRequest;
use App\Http\Requests\StoreCustomImageRequest;
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

    /**
     * Create a custom image for a book using the Flux 2 JSON schema.
     * The prompt should be a JSON string containing the full scene description.
     */
    public function createCustomImage(StoreCustomImageRequest $request, Book $book): JsonResponse
    {
        $this->authorize('update', $book);

        $prompt = $request->input('prompt');
        $characterImageUrls = $request->input('character_image_urls', []);
        $aspectRatio = $request->input('aspect_ratio');

        // Manual images always use the Manual type
        $imageType = ImageType::Manual;

        $image = $this->imageService->store([
            'book_id' => $book->id,
            'user_id' => $book->user_id,
            'profile_id' => $book->profile_id,
            'type' => $imageType,
            'prompt' => $prompt,
            'status' => ImageStatus::Pending,
            'aspect_ratio' => $aspectRatio ?? $imageType->aspectRatio(),
        ]);

        GenerateImageJob::dispatch($image, $characterImageUrls)->onQueue('images');

        return response()->json([
            'message' => 'Custom image generation started',
            'image' => [
                'id' => $image->id,
                'book_id' => $image->book_id,
                'type' => $image->type->value,
                'status' => $image->status->value,
                'prompt' => $image->prompt,
                'full_url' => $image->full_url,
                'aspect_ratio' => $image->aspect_ratio,
                'created_at' => $image->created_at?->toISOString(),
            ],
        ], 201);
    }

    /**
     * Edit an existing image by creating a new one with modified prompt.
     * Transfers all associations from the original image to the new one and deletes the original.
     */
    public function edit(EditImageRequest $request, Image $image): JsonResponse
    {
        if ($image->book_id) {
            $this->authorize('update', $image->book);
        }

        $prompt = $request->input('prompt');
        $characterImageUrls = $request->input('character_image_urls', []);
        $referenceImageUrls = $request->input('reference_image_urls', []);
        $aspectRatio = $request->input('aspect_ratio');

        // Reference image URLs are sent directly from the frontend
        // (already includes the original image URL if the user enabled it)
        // Combine character portraits and reference images for the generation job
        $inputImageUrls = array_merge($referenceImageUrls, $characterImageUrls);

        // Create a new image record with the updated prompt
        $newImage = $this->imageService->store([
            'book_id' => $image->book_id,
            'chapter_id' => $image->chapter_id,
            'character_id' => $image->character_id,
            'user_id' => $image->user_id,
            'profile_id' => $image->profile_id,
            'type' => $image->type,
            'prompt' => $prompt,
            'status' => ImageStatus::Pending,
            'paragraph_index' => $image->paragraph_index,
            'aspect_ratio' => $aspectRatio ?? $image->aspect_ratio,
        ]);

        // Transfer associations from the old image to the new one
        $this->transferImageAssociations($image, $newImage);

        // Delete the original image
        $image->delete();

        // Dispatch the generation job
        GenerateImageJob::dispatch($newImage, $inputImageUrls)->onQueue('images');

        return response()->json([
            'message' => 'Image edit started',
            'image' => [
                'id' => $newImage->id,
                'book_id' => $newImage->book_id,
                'chapter_id' => $newImage->chapter_id,
                'character_id' => $newImage->character_id,
                'type' => $newImage->type->value,
                'status' => $newImage->status->value,
                'prompt' => $newImage->prompt,
                'full_url' => $newImage->full_url,
                'aspect_ratio' => $newImage->aspect_ratio,
                'paragraph_index' => $newImage->paragraph_index,
                'created_at' => $newImage->created_at?->toISOString(),
            ],
        ], 201);
    }

    /**
     * Transfer all associations from the original image to the new image.
     * This updates foreign keys on books, chapters, and characters.
     */
    protected function transferImageAssociations(Image $oldImage, Image $newImage): void
    {
        // Transfer book cover association
        if ($oldImage->type === ImageType::BookCover && $oldImage->book_id) {
            Book::where('cover_image_id', $oldImage->id)->update([
                'cover_image_id' => $newImage->id,
            ]);
        }

        // Transfer chapter header association
        if ($oldImage->type === ImageType::ChapterHeader && $oldImage->chapter_id) {
            Chapter::where('header_image_id', $oldImage->id)->update([
                'header_image_id' => $newImage->id,
            ]);
        }

        // Transfer character portrait association
        if ($oldImage->type === ImageType::CharacterPortrait && $oldImage->character_id) {
            Character::where('portrait_image_id', $oldImage->id)->update([
                'portrait_image_id' => $newImage->id,
            ]);
        }

        // For chapter inline images, the association is via chapter_id and paragraph_index
        // which are already copied to the new image, so no additional transfer needed
    }
}
