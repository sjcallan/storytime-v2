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

    /** @var \App\Services\Builder\BookBuilderService */
    protected $bookBuilderService;

    /** @var \App\Services\Builder\CharacterBuilderService */
    protected $characterBuilderService;

    public function __construct(
        BookRepository $bookRepository,
        ChatService $chatService,
        BookBuilderService $bookBuilderService,
        CharacterBuilderService $characterBuilderService
    ) {
        $this->repository = $bookRepository;
        $this->chatService = $chatService;
        $this->bookBuilderService = $bookBuilderService;
        $this->characterBuilderService = $characterBuilderService;
    }

    public function getAllByUserId(int $userId, ?array $fields = null, ?array $options = null)
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

        $metaData = $this->bookBuilderService->getBookMetaData($bookId, $userCharacters);

        if (! empty($metaData['title'])) {
            $this->updateById($bookId, ['title' => $metaData['title']]);
            Log::info("Book title updated: {$metaData['title']}");
        }

        if (! empty($metaData['characters'])) {
            $characterResponse = json_encode(['characters' => $metaData['characters']]);
            $this->characterBuilderService->saveCharacterResponse($characterResponse, $bookId);
            Log::info('Book characters saved: '.count($metaData['characters']).' characters');
        }

        return $metaData;
    }
}
