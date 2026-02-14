<?php

namespace App\Services\Image;

use App\Enums\ImageStatus;
use App\Enums\ImageType;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Character;
use App\Models\Image;
use App\Repositories\Image\ImageRepository;
use App\Traits\Service\Creatable;
use App\Traits\Service\Deletable;
use App\Traits\Service\Gettable;
use App\Traits\Service\Updatable;
use Illuminate\Database\Eloquent\Collection;

class ImageService
{
    use Creatable, Deletable, Gettable, Updatable;

    /** @var \App\Repositories\Image\ImageRepository */
    protected $repository;

    public function __construct(ImageRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Create a new book cover image record.
     */
    public function createBookCoverImage(Book $book, ?string $prompt = null): Image
    {
        return $this->store([
            'book_id' => $book->id,
            'user_id' => $book->user_id,
            'profile_id' => $book->profile_id,
            'type' => ImageType::BookCover,
            'prompt' => $prompt,
            'status' => ImageStatus::Pending,
            'aspect_ratio' => ImageType::BookCover->aspectRatio(),
        ]);
    }

    /**
     * Create a new chapter header image record.
     */
    public function createChapterHeaderImage(Chapter $chapter, ?string $prompt = null): Image
    {
        return $this->store([
            'book_id' => $chapter->book_id,
            'chapter_id' => $chapter->id,
            'user_id' => $chapter->user_id,
            'profile_id' => $chapter->profile_id,
            'type' => ImageType::ChapterHeader,
            'prompt' => $prompt,
            'status' => ImageStatus::Pending,
            'aspect_ratio' => $this->getEffectiveAspectRatio(ImageType::ChapterHeader->aspectRatio()),
        ]);
    }

    /**
     * Create a new chapter inline image record.
     */
    public function createChapterInlineImage(Chapter $chapter, int $paragraphIndex, ?string $prompt = null): Image
    {
        return $this->store([
            'book_id' => $chapter->book_id,
            'chapter_id' => $chapter->id,
            'user_id' => $chapter->user_id,
            'profile_id' => $chapter->profile_id,
            'type' => ImageType::ChapterInline,
            'prompt' => $prompt,
            'status' => ImageStatus::Pending,
            'paragraph_index' => $paragraphIndex,
            'aspect_ratio' => $this->getEffectiveAspectRatio(ImageType::ChapterInline->aspectRatio()),
        ]);
    }

    /**
     * Create a new character portrait image record.
     */
    public function createCharacterPortraitImage(Character $character, ?string $prompt = null): Image
    {
        return $this->store([
            'book_id' => $character->book_id,
            'character_id' => $character->id,
            'user_id' => $character->user_id,
            'profile_id' => $character->book?->profile_id,
            'type' => ImageType::CharacterPortrait,
            'prompt' => $prompt,
            'status' => ImageStatus::Pending,
            'aspect_ratio' => ImageType::CharacterPortrait->aspectRatio(),
        ]);
    }

    /**
     * Get the effective aspect ratio for the current model.
     *
     * Custom models produce better results in 9:16 portrait orientation,
     * while Flux 2 should use 16:9 landscape.
     */
    protected function getEffectiveAspectRatio(string $aspectRatio): string
    {
        $useCustomModel = (bool) config('services.replicate.use_custom_model', false);

        if ($useCustomModel && $aspectRatio === '16:9') {
            return '9:16';
        }

        return $aspectRatio;
    }

    /**
     * Mark an image as processing.
     */
    public function markProcessing(Image $image): Image
    {
        return $this->updateById($image->id, [
            'status' => ImageStatus::Processing,
            'error' => null,
        ]);
    }

    /**
     * Mark an image as complete with the generated URL.
     */
    public function markComplete(Image $image, string $imageUrl): Image
    {
        return $this->updateById($image->id, [
            'status' => ImageStatus::Complete,
            'image_url' => $imageUrl,
            'error' => null,
        ]);
    }

    /**
     * Mark an image as failed with an error message.
     */
    public function markError(Image $image, string $error): Image
    {
        return $this->updateById($image->id, [
            'status' => ImageStatus::Error,
            'error' => $error,
        ]);
    }

    /**
     * Mark an image as cancelled.
     */
    public function markCancelled(Image $image): Image
    {
        return $this->updateById($image->id, [
            'status' => ImageStatus::Cancelled,
        ]);
    }

    /**
     * Reset an image for regeneration.
     */
    public function resetForRegeneration(Image $image): Image
    {
        return $this->updateById($image->id, [
            'status' => ImageStatus::Pending,
            'image_url' => null,
            'error' => null,
        ]);
    }

    /**
     * Get all images for a book.
     */
    public function getAllByBookId(string $bookId, ?array $fields = null, ?array $options = null): Collection
    {
        return $this->repository->getAllByBookId($bookId, $fields, $options);
    }

    /**
     * Get all images for a chapter.
     */
    public function getAllByChapterId(string $chapterId, ?array $fields = null, ?array $options = null): Collection
    {
        return $this->repository->getAllByChapterId($chapterId, $fields, $options);
    }

    /**
     * Get all images for a character.
     */
    public function getAllByCharacterId(string $characterId, ?array $fields = null, ?array $options = null): Collection
    {
        return $this->repository->getAllByCharacterId($characterId, $fields, $options);
    }

    /**
     * Get the book cover image.
     */
    public function getBookCover(string $bookId): ?Image
    {
        return $this->repository->getBookCover($bookId);
    }

    /**
     * Get the chapter header image.
     */
    public function getChapterHeader(string $chapterId): ?Image
    {
        return $this->repository->getChapterHeader($chapterId);
    }

    /**
     * Get all inline images for a chapter.
     */
    public function getChapterInlineImages(string $chapterId): Collection
    {
        return $this->repository->getChapterInlineImages($chapterId);
    }

    /**
     * Get the character portrait image.
     */
    public function getCharacterPortrait(string $characterId): ?Image
    {
        return $this->repository->getCharacterPortrait($characterId);
    }

    /**
     * Get an inline image by paragraph index.
     */
    public function getInlineImageByParagraphIndex(string $chapterId, int $paragraphIndex): ?Image
    {
        return $this->repository->getInlineImageByParagraphIndex($chapterId, $paragraphIndex);
    }

    /**
     * Get or create a book cover image.
     */
    public function getOrCreateBookCover(Book $book): Image
    {
        $existing = $this->getBookCover($book->id);

        if ($existing) {
            return $existing;
        }

        return $this->createBookCoverImage($book);
    }

    /**
     * Get or create a chapter header image.
     */
    public function getOrCreateChapterHeader(Chapter $chapter): Image
    {
        $existing = $this->getChapterHeader($chapter->id);

        if ($existing) {
            return $existing;
        }

        return $this->createChapterHeaderImage($chapter);
    }

    /**
     * Get or create a character portrait image.
     */
    public function getOrCreateCharacterPortrait(Character $character): Image
    {
        $existing = $this->getCharacterPortrait($character->id);

        if ($existing) {
            return $existing;
        }

        return $this->createCharacterPortraitImage($character);
    }

    /**
     * Create a new Image record based on an existing one.
     * Copies all relevant fields but starts with fresh status.
     */
    public function createFromExisting(Image $existingImage): Image
    {
        return $this->store([
            'book_id' => $existingImage->book_id,
            'chapter_id' => $existingImage->chapter_id,
            'character_id' => $existingImage->character_id,
            'user_id' => $existingImage->user_id,
            'profile_id' => $existingImage->profile_id,
            'type' => $existingImage->type,
            'prompt' => $existingImage->prompt,
            'status' => ImageStatus::Pending,
            'paragraph_index' => $existingImage->paragraph_index,
            'aspect_ratio' => $existingImage->aspect_ratio,
        ]);
    }

    /**
     * Update the foreign key on the associated entity to point to this image.
     */
    public function updateEntityImageReference(Image $image): void
    {
        switch ($image->type) {
            case ImageType::BookCover:
                if ($image->book_id) {
                    Book::where('id', $image->book_id)->update([
                        'cover_image_id' => $image->id,
                    ]);
                }
                break;

            case ImageType::ChapterHeader:
                if ($image->chapter_id) {
                    Chapter::where('id', $image->chapter_id)->update([
                        'header_image_id' => $image->id,
                    ]);
                }
                break;

            case ImageType::CharacterPortrait:
                if ($image->character_id) {
                    Character::where('id', $image->character_id)->update([
                        'portrait_image_id' => $image->id,
                    ]);
                }
                break;

            case ImageType::ChapterInline:
                // Inline images don't have a direct foreign key reference
                // They're associated via chapter_id and paragraph_index
                break;
        }
    }
}
