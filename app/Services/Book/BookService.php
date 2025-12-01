<?php

namespace App\Services\Book;

use App\Repositories\Book\BookRepository;
use App\Services\Builder\BookBuilderService;
use App\Services\Builder\CharacterBuilderService;
use App\Services\OpenAi\ChatService;
use App\Traits\Service\Creatable;
use App\Traits\Service\Deletable;
use App\Traits\Service\Gettable;
use App\Traits\Service\Updatable;
use Illuminate\Support\Facades\Log;

class BookService
{
    use Creatable, Deletable, Gettable, Updatable;

    /** @var \App\Repositories\Book\BookRepository */
    protected $repository;

    /** @var \App\Services\OpenAi\ChatService */
    protected $chatService;

    public function __construct(
        BookRepository $bookRepository,
        ChatService $chatService
    ) {
        $this->repository = $bookRepository;
        $this->chatService = $chatService;
    }

    /**
     * Get BookBuilderService on-demand to avoid circular dependency.
     */
    protected function getBookBuilderService(): BookBuilderService
    {
        return app(BookBuilderService::class);
    }

    /**
     * Get CharacterBuilderService on-demand to avoid circular dependency.
     */
    protected function getCharacterBuilderService(): CharacterBuilderService
    {
        return app(CharacterBuilderService::class);
    }

    public function getAllByUserId(string $userId, ?array $fields = null, ?array $options = null)
    {
        return $this->repository->getAllByUserId($userId, $fields, $options);
    }

    public function getAllPublished(?array $fields = null, ?array $options = null)
    {
        return $this->repository->getAllPublished($fields, $options);
    }

    public function createBookMetaDataByBookId(string $bookId, string $userCharacters = ''): array
    {
        $book = $this->getById($bookId);

        if (! $book) {
            Log::warning("Book not found: {$bookId}");

            return [];
        }

        Log::info("Creating book metadata for book: {$bookId}");

        $metaData = $this->getBookBuilderService()->getBookMetaData($bookId, $userCharacters);

        if (! empty($metaData['title'])) {
            $this->updateById($bookId, ['title' => $metaData['title']]);
            Log::info("Book title updated: {$metaData['title']}");
        }

        if (! empty($metaData['characters'])) {
            $characterResponse = json_encode(['characters' => $metaData['characters']]);
            $this->getCharacterBuilderService()->saveCharacterResponse($characterResponse, $bookId);
            Log::info('Book characters saved: '.count($metaData['characters']).' characters');
        }

        return $metaData;
    }
}
