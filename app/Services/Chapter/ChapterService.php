<?php

namespace App\Services\Chapter;

use App\Repositories\Chapter\ChapterRepository;
use App\Services\Book\BookService;
use App\Services\Builder\ChapterBuilderService;
use App\Services\OpenAi\ChatService;
use App\Services\OpenAi\DalleService;
use App\Services\RequestLog\RequestLogService;
use App\Traits\Service\Creatable;
use App\Traits\Service\Deletable;
use App\Traits\Service\Gettable;
use App\Traits\Service\Updatable;
use Illuminate\Support\Facades\Log;

class ChapterService
{
    use Gettable, Creatable, Updatable, Deletable;

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
     * @param \App\Repositories\Chapter\ChapterRepository $ChapterRepository
     */
    public function __construct(ChapterRepository $chapterRepository, ChatService $chatService, BookService $bookService, DalleService $dalleService, RequestLogService $requestLogService) 
    {
        $this->repository = $chapterRepository;
        $this->chatService = $chatService;
        $this->bookService = $bookService;
        $this->dalleService = $dalleService;
        $this->requestLogService = $requestLogService;
    }

    /**
     * @param int $userId
     * @param array $fields
     * @param array $options
     */
    public function getAllByUserId(int $userId, array $fields = null, array $options = null)
    {
        return $this->repository->getAllByUserId($userId, $fields, $options);
    }

    /**
     * @param string $bookId
     * @param array $fields
     * @param array $options
     */
    public function getAllByBookId(string $bookId, array $fields = null, array $options = null)
    {
        return $this->repository->getAllByBookId($bookId, $fields, $options);
    }

}