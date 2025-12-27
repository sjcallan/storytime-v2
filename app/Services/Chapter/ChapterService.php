<?php

namespace App\Services\Chapter;

use App\Repositories\Chapter\ChapterRepository;
use App\Services\Book\BookService;
use App\Services\OpenAi\ChatService;
use App\Services\RequestLog\RequestLogService;
use App\Traits\Service\Creatable;
use App\Traits\Service\Deletable;
use App\Traits\Service\Gettable;
use App\Traits\Service\Updatable;

class ChapterService
{
    use Creatable, Deletable, Gettable, Updatable;

    /** @var \App\Repositories\Chapter\ChapterRepository */
    protected $repository;

    /** @var \App\Services\OpenAi\ChatService */
    protected $chatService;

    /** @var \App\Services\Book\BookService */
    protected $bookService;

    /** @var \App\Services\Builder\ChapterBuilderService */
    protected $chapterBuilderService;

    /** @var \App\Services\RequestLog\RequestLogService */
    protected $requestLogService;

    /**
     * @param  \App\Repositories\Chapter\ChapterRepository  $ChapterRepository
     */
    public function __construct(ChapterRepository $chapterRepository, ChatService $chatService, BookService $bookService, RequestLogService $requestLogService)
    {
        $this->repository = $chapterRepository;
        $this->chatService = $chatService;
        $this->bookService = $bookService;
        $this->requestLogService = $requestLogService;
    }

    public function getAllByUserId(string $userId, ?array $fields = null, ?array $options = null)
    {
        return $this->repository->getAllByUserId($userId, $fields, $options);
    }

    public function getAllByBookId(string $bookId, ?array $fields = null, ?array $options = null)
    {
        return $this->repository->getAllByBookId($bookId, $fields, $options);
    }

    /**
     * Get a chapter by book ID and sort number.
     */
    public function getByBookIdAndSort(string $bookId, int $sort, ?array $fields = null, ?array $options = null)
    {
        return $this->repository->getByBookIdAndSort($bookId, $sort, $fields, $options);
    }

    /**
     * Get the total count of complete chapters for a book.
     */
    public function getCompleteChapterCount(string $bookId): int
    {
        return $this->repository->getCompleteChapterCount($bookId);
    }

    /**
     * Get the last complete chapter for a book.
     */
    public function getLastChapter(string $bookId): ?\App\Models\Chapter
    {
        return $this->repository->getLastChapter($bookId);
    }

    /**
     * Get the chapter with the highest sort order (any status).
     */
    public function getMostRecentChapter(string $bookId): ?\App\Models\Chapter
    {
        return $this->repository->getMostRecentChapter($bookId);
    }
}
