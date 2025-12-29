<?php

namespace App\Services\Book;

use App\Models\Character;
use App\Repositories\Book\BookRepository;
use App\Services\Ai\AiManager;
use App\Services\Builder\BookBuilderService;
use App\Services\Builder\CharacterBuilderService;
use App\Services\OpenAi\ChatService;
use App\Traits\Service\Creatable;
use App\Traits\Service\Deletable;
use App\Traits\Service\Gettable;
use App\Traits\Service\Updatable;
use Illuminate\Support\Facades\Log;
use Throwable;

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

    /**
     * Generate a creative inspiration based on book type, genre, age level, and inspiration type.
     *
     * @param  array{type: string, genre: string, age_level: string|int, inspiration_type?: string, plot?: string, first_chapter_prompt?: string}  $params
     */
    public function generatePlotInspiration(array $params): ?string
    {
        try {
            $chatService = app(AiManager::class)->chat();
            $chatService->resetMessages();
            $chatService->setMaxTokens(200);
            $chatService->setTemperature(0.9);

            $type = $params['type'] ?? 'story';
            $genre = $params['genre'] ?? 'adventure';
            $ageLevel = $params['age_level'] ?? 12;
            $inspirationType = $params['inspiration_type'] ?? 'plot';
            $plot = $params['plot'] ?? '';
            $firstChapterPrompt = $params['first_chapter_prompt'] ?? '';

            $bookTypeLabel = match ($type) {
                'chapter' => 'chapter book',
                'theatre' => 'theatre play',
                'screenplay' => 'screenplay',
                default => 'short story',
            };

            $genreLabel = str_replace('_', ' ', $genre);

            $ageDescription = match (true) {
                (int) $ageLevel <= 10 => 'children (ages 7-10)',
                (int) $ageLevel <= 13 => 'pre-teens (ages 11-13)',
                (int) $ageLevel <= 17 => 'teenagers (ages 14-17)',
                default => 'adults (18+)',
            };

            $systemPrompt = $this->buildInspirationSystemPrompt(
                $inspirationType,
                $genreLabel,
                $bookTypeLabel,
                $ageDescription,
                $plot,
                $firstChapterPrompt
            );

            $userPrompt = $this->buildInspirationUserPrompt(
                $inspirationType,
                $genreLabel,
                $bookTypeLabel,
                $ageDescription
            );

            $chatService->setContext($systemPrompt);
            $chatService->addUserMessage($userPrompt);

            $result = $chatService->chat();

            if (! empty($result['error'])) {
                Log::warning('[BookService::generatePlotInspiration] AI error', [
                    'error' => $result['error'],
                    'inspiration_type' => $inspirationType,
                ]);

                return null;
            }

            $suggestion = trim($result['completion'] ?? '');

            $suggestion = trim($suggestion, "\"'\u{201C}\u{201D}\u{2018}\u{2019}");

            return $suggestion ?: null;
        } catch (Throwable $e) {
            Log::error('[BookService::generatePlotInspiration] Exception', [
                'exception_class' => get_class($e),
                'exception_message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Build the system prompt based on inspiration type.
     */
    protected function buildInspirationSystemPrompt(
        string $inspirationType,
        string $genreLabel,
        string $bookTypeLabel,
        string $ageDescription,
        string $plot,
        string $firstChapterPrompt
    ): string {
        $baseRules = <<<RULES
- Keep it to 1-2 sentences maximum (30-50 words)
- Make it specific and evocative, not generic
- Make it age-appropriate for {$ageDescription}
- Do NOT include any preamble, quotation marks, or explanation
- Just output the suggestion directly
RULES;

        if ($inspirationType === 'opening') {
            $plotContext = $plot ? "\n\nThe story plot is: {$plot}" : '';

            return <<<PROMPT
You are a creative writing assistant helping someone craft an engaging opening for their story.
Generate ONE creative idea for how a {$genreLabel} {$bookTypeLabel} appropriate for {$ageDescription} could begin.{$plotContext}

Rules:
{$baseRules}
- Describe an intriguing opening moment or action
- Create immediate tension or curiosity
- Set the tone for the genre
PROMPT;
        }

        if ($inspirationType === 'location') {
            $plotContext = $plot ? "\n\nThe story plot is: {$plot}" : '';
            $openingContext = $firstChapterPrompt ? "\n\nThe opening scene idea is: {$firstChapterPrompt}" : '';

            return <<<PROMPT
You are a creative writing assistant helping someone create a vivid setting for their story.
Generate ONE creative setting/location description for a {$genreLabel} {$bookTypeLabel} appropriate for {$ageDescription}.{$plotContext}{$openingContext}

Rules:
{$baseRules}
- Describe a specific, atmospheric place
- Include sensory details that bring it to life
- Make it fit the genre and story tone
PROMPT;
        }

        return <<<PROMPT
You are a creative writing assistant helping someone come up with an engaging story idea.
Generate ONE creative, original plot idea for a {$genreLabel} {$bookTypeLabel} appropriate for {$ageDescription}.

Rules:
- Keep it to 2-3 sentences maximum (50-80 words)
- Make it specific and unique, not generic
- Include an interesting hook or twist
- Make it age-appropriate for {$ageDescription}
- Don't include character names - let the user create those
- Write in a way that inspires the user to build on the idea
- Do NOT include any preamble, quotation marks, or explanation
- Just output the plot idea directly
PROMPT;
    }

    /**
     * Build the user prompt based on inspiration type.
     */
    protected function buildInspirationUserPrompt(
        string $inspirationType,
        string $genreLabel,
        string $bookTypeLabel,
        string $ageDescription
    ): string {
        if ($inspirationType === 'opening') {
            return "Generate an exciting opening scene idea for this {$genreLabel} {$bookTypeLabel}:";
        }

        if ($inspirationType === 'location') {
            return "Generate an evocative setting/location for this {$genreLabel} {$bookTypeLabel}:";
        }

        return "Generate an exciting {$genreLabel} {$bookTypeLabel} plot idea for {$ageDescription}:";
    }
}
