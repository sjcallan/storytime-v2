<?php

namespace App\Services\Chapter;

use App\Repositories\Chapter\ChapterRepository;
use App\Services\Book\BookService;
use App\Services\OpenAi\ChatService;
use App\Services\OpenAi\DalleService;
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

    /** @var \App\Services\OpenAi\DalleService */
    protected $dalleService;

    /** @var \App\Services\RequestLog\RequestLogService */
    protected $requestLogService;

    /**
     * @param  \App\Repositories\Chapter\ChapterRepository  $ChapterRepository
     */
    public function __construct(ChapterRepository $chapterRepository, ChatService $chatService, BookService $bookService, DalleService $dalleService, RequestLogService $requestLogService)
    {
        $this->repository = $chapterRepository;
        $this->chatService = $chatService;
        $this->bookService = $bookService;
        $this->dalleService = $dalleService;
        $this->requestLogService = $requestLogService;
    }

    public function getAllByUserId(int $userId, ?array $fields = null, ?array $options = null)
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
}
