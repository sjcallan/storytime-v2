<?php

namespace App\Services\Book;

use App\Models\Character;
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

        $this->updateById($bookId, [
            'title' => $metaData['title'],
            'summary' => $metaData['summary'],
        ]);

        if (! empty($metaData['characters'])) {
            $this->saveBookCharacters($book, $metaData['characters']);
            Log::info('Book characters saved: '.count($metaData['characters']).' characters');
        }

        return $metaData;
    }

    /**
     * Save characters directly to the characters table for initial book creation.
     * This is different from chapter-level character tracking which uses chapter_character pivot.
     *
     * @param  \App\Models\Book  $book
     * @param  array<array{name: string, description?: string, gender?: string, age?: string, nationality?: string}>  $characters
     */
    protected function saveBookCharacters($book, array $characters): void
    {
        foreach ($characters as $characterData) {
            Character::create([
                'book_id' => $book->id,
                'user_id' => $book->user_id,
                'type' => 'book',
                'name' => $characterData['name'] ?? '',
                'description' => $characterData['description'] ?? '',
                'gender' => $characterData['gender'] ?? '',
                'age' => $characterData['age'] ?? '',
                'backstory' => $characterData['backstory'] ?? '',
                'nationality' => $characterData['nationality'] ?? '',
            ]);
        }
    }
}
