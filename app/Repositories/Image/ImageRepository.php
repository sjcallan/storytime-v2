<?php

namespace App\Repositories\Image;

use App\Enums\ImageStatus;
use App\Enums\ImageType;
use App\Models\Image;
use App\Traits\Repository\Creatable;
use App\Traits\Repository\Deletable;
use App\Traits\Repository\Gettable;
use App\Traits\Repository\Updatable;
use Illuminate\Database\Eloquent\Collection;

class ImageRepository
{
    use Creatable, Deletable, Gettable, Updatable;

    /** @var \App\Models\Image */
    protected $model;

    protected $query;

    public function __construct(Image $model)
    {
        $this->model = $model;
        $this->query = $model;
    }

    /**
     * Get all images for a book.
     */
    public function getAllByBookId(string $bookId, ?array $fields = null, ?array $options = null): Collection
    {
        $this->reset();
        $this->setFields($fields);
        $this->setOptions($options);

        return $this->query->where('book_id', $bookId)->get();
    }

    /**
     * Get all images for a chapter.
     */
    public function getAllByChapterId(string $chapterId, ?array $fields = null, ?array $options = null): Collection
    {
        $this->reset();
        $this->setFields($fields);
        $this->setOptions($options);

        return $this->query->where('chapter_id', $chapterId)->get();
    }

    /**
     * Get all images for a character.
     */
    public function getAllByCharacterId(string $characterId, ?array $fields = null, ?array $options = null): Collection
    {
        $this->reset();
        $this->setFields($fields);
        $this->setOptions($options);

        return $this->query->where('character_id', $characterId)->get();
    }

    /**
     * Get images by type for a book.
     */
    public function getByBookIdAndType(string $bookId, ImageType $type, ?array $fields = null, ?array $options = null): Collection
    {
        $this->reset();
        $this->setFields($fields);
        $this->setOptions($options);

        return $this->query
            ->where('book_id', $bookId)
            ->where('type', $type)
            ->get();
    }

    /**
     * Get images by type for a chapter.
     */
    public function getByChapterIdAndType(string $chapterId, ImageType $type, ?array $fields = null, ?array $options = null): Collection
    {
        $this->reset();
        $this->setFields($fields);
        $this->setOptions($options);

        return $this->query
            ->where('chapter_id', $chapterId)
            ->where('type', $type)
            ->get();
    }

    /**
     * Get the book cover image for a book.
     */
    public function getBookCover(string $bookId): ?Image
    {
        return $this->model
            ->where('book_id', $bookId)
            ->where('type', ImageType::BookCover)
            ->first();
    }

    /**
     * Get the chapter header image.
     */
    public function getChapterHeader(string $chapterId): ?Image
    {
        return $this->model
            ->where('chapter_id', $chapterId)
            ->where('type', ImageType::ChapterHeader)
            ->first();
    }

    /**
     * Get all inline images for a chapter.
     */
    public function getChapterInlineImages(string $chapterId): Collection
    {
        return $this->model
            ->where('chapter_id', $chapterId)
            ->where('type', ImageType::ChapterInline)
            ->orderBy('paragraph_index')
            ->get();
    }

    /**
     * Get the character portrait image.
     */
    public function getCharacterPortrait(string $characterId): ?Image
    {
        return $this->model
            ->where('character_id', $characterId)
            ->where('type', ImageType::CharacterPortrait)
            ->first();
    }

    /**
     * Get all pending or processing images.
     */
    public function getInProgressImages(): Collection
    {
        return $this->model
            ->whereIn('status', [ImageStatus::Pending, ImageStatus::Processing])
            ->get();
    }

    /**
     * Get inline image by chapter and paragraph index.
     */
    public function getInlineImageByParagraphIndex(string $chapterId, int $paragraphIndex): ?Image
    {
        return $this->model
            ->where('chapter_id', $chapterId)
            ->where('type', ImageType::ChapterInline)
            ->where('paragraph_index', $paragraphIndex)
            ->first();
    }
}
