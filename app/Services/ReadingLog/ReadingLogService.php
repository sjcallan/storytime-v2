<?php

namespace App\Services\ReadingLog;

use App\Repositories\ReadingLog\ReadingLogRepository;
use App\Traits\Service\Creatable;
use App\Traits\Service\Deletable;
use App\Traits\Service\Gettable;
use App\Traits\Service\Updatable;

class ReadingLogService
{
    use Gettable, Creatable, Updatable, Deletable;

    /** @var \App\Repositories\ReadingLog\ReadingLogRepository */
    protected $repository;

    /**
     * @param \App\Repositories\ReadingLog\ReadingLogRepository $readingLogRepository
     */
    public function __construct(ReadingLogRepository $readingLogRepository ) 
    {
        $this->repository = $readingLogRepository;
    }

    /**
     * 
     */
    public function getBookmark(string $userId, string $bookId, array $fields = null, array $options = null)
    {
        return $this->repository->getBookmark($userId, $bookId, $fields, $options);
    }

    /**
     * 
     */
    public function setBookmark(string $userId, string $bookId, string $chapterId = null)
    {
        return $this->repository->store([
            'user_id' => $userId,
            'book_id' => $bookId,
            'chapter_id' => $chapterId
        ]);
    }

}