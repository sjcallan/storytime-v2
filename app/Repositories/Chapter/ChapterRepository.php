<?php

namespace App\Repositories\Chapter;

use App\Models\Chapter;
use App\Traits\Repository\Creatable;
use App\Traits\Repository\Deletable;
use App\Traits\Repository\Gettable;
use App\Traits\Repository\Updatable;

class ChapterRepository
{
    use Creatable, Deletable, Gettable, Updatable;

    /** @var \App\Models\Chapter */
    protected $model;

    protected $query;

    public function __construct(Chapter $model)
    {
        $this->model = $model;
        $this->query = $model;
    }

    public function getAllByUserId(string $userId, ?array $fields = null, ?array $options = null)
    {
        $this->reset();
        $this->setFields($fields);
        $this->setOptions($options);

        return $this->query->where('owner_id', $userId)->get();
    }

    public function getAllByBookId(string $bookId, ?array $fields = null, ?array $options = null)
    {
        $this->reset();
        $this->setFields($fields);
        $this->setOptions($options);

        return $this->query->where('book_id', $bookId)->get();
    }

    /**
     * Get a chapter by book ID and sort number.
     */
    public function getByBookIdAndSort(string $bookId, int $sort, ?array $fields = null, ?array $options = null)
    {
        $this->reset();
        $this->setFields($fields);
        $this->setOptions($options);

        return $this->query
            ->where('book_id', $bookId)
            ->where('sort', $sort)
            ->where('status', 'complete')
            ->first();
    }

    /**
     * Get the total count of complete chapters for a book.
     */
    public function getCompleteChapterCount(string $bookId): int
    {
        return $this->model
            ->where('book_id', $bookId)
            ->where('status', 'complete')
            ->count();
    }

    /**
     * Get the last complete chapter for a book.
     */
    public function getLastChapter(string $bookId): ?Chapter
    {
        return $this->model
            ->where('book_id', $bookId)
            ->where('status', 'complete')
            ->orderBy('sort', 'desc')
            ->first();
    }

    /**
     * Get the chapter with the highest sort order for a book (any status).
     */
    public function getMostRecentChapter(string $bookId): ?Chapter
    {
        return $this->model
            ->where('book_id', $bookId)
            ->orderBy('sort', 'desc')
            ->first();
    }
}
