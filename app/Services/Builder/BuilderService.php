<?php

namespace App\Services\Builder;

use App\Services\Ai\AiManager;
use App\Services\Book\BookService;
use App\Services\Chapter\ChapterService;
use App\Services\Character\CharacterService;
use App\Services\Replicate\ReplicateApiService;
use App\Services\StabilityAI\StabilityAIService;
use App\Traits\Service\SavesImagesToS3;
use Illuminate\Support\Facades\Log;

class BuilderService
{
    use SavesImagesToS3;

    /** @var \App\Services\Ai\Contracts\AiChatServiceInterface */
    protected $chatService;

    /** @var \App\Services\Book\BookService */
    protected $bookService;

    /** @var \App\Services\Chapter\ChapterService */
    protected $chapterService;

    /** @var \App\Services\Character\CharacterService */
    protected $characterService;

    protected $stabilityAIService;

    protected $replicateApiService;

    public function __construct(AiManager $aiManager, BookService $bookService, ChapterService $chapterService, StabilityAIService $stabilityAIService, CharacterService $characterService, ReplicateApiService $replicateApiService)
    {
        $this->chatService = $aiManager->chat();
        $this->bookService = $bookService;
        $this->chapterService = $chapterService;
        $this->characterService = $characterService;
        $this->stabilityAIService = $stabilityAIService;
        $this->replicateApiService = $replicateApiService;
    }

    /**
     * Get the art style string based on book's age level and genre.
     *
     * @return string The style directive for image generation
     */
    public function getImageStyle(string $bookId): string
    {
        $book = $this->bookService->getById($bookId, ['age_level', 'genre']);

        $style = '';

        if ($book->age_level <= 12) {
            $style = 'in a cartoon style';
        }

        if ($book->genre == 'Children') {
            $style .= ' in a bright cartoon style ';
        }

        if ($book->genre == 'Science fiction') {
            $style .= ' in the style of star trek ';
        }

        if ($book->genre == 'Romance') {
            $style .= ' fine art of, ';
        }

        if ($book->genre == 'Horror') {
            $style .= ' gothic art of, ';
        }

        if ($book->genre == 'Fantasy') {
            $style .= ' in the style of Neil Gaiman,';
        }

        return trim($style);
    }

    public function getImage(string $bookId, string $chapterId, string $prompt, bool $getImagePrompt = false, bool $includeCharacterImages = true)
    {
        $book = $this->bookService->getById($bookId, null, ['with' => 'chapters', 'characters']);

        $style = $this->getImageStyle($bookId);

        $imagePromptCompletion = $prompt;

        if ($getImagePrompt) {
            $this->chatService->resetMessages();
            $this->chatService->addUserMessage('What happened?');
            $this->chatService->addAssistantMessage($prompt);

            $this->chatService->addUserMessage('In one sentence, what prompt should I give to our image generation service to draw these characters in this scene. Exclude character names.');
            $imagePrompt = $this->chatService->chat();

            $this->chatService->trackRequestLog($bookId, $chapterId, $book->user_id, 'image_prompt', $imagePrompt, $book->profile_id);

            $prompt = $imagePrompt['completion'];
            $prompt = $this->stripQuotes($prompt);
            $imagePromptCompletion = $imagePrompt['completion'];

            Log::debug('Image prompt: '.$style.' '.$prompt);
        }

        // get character images to use as input images
        $characterImages = [];

        if ($includeCharacterImages) {
            foreach ($book->characters as $character) {
                if ($character->portrait_image) {
                    // Use the CloudFront URL directly if it's already a full URL
                    $portraitUrl = $character->portrait_image;
                    if (! str_starts_with($portraitUrl, 'http')) {
                        $portraitUrl = $this->getCloudFrontImageUrl($portraitUrl);
                    }
                    if ($portraitUrl) {
                        $characterImages[] = $portraitUrl;
                    }
                }
            }
        }

        $imageResponse = $this->replicateApiService->generateImage($style.' '.$prompt, $characterImages);

        if (! $imageResponse || ! isset($imageResponse['url']) || ! $imageResponse['url']) {
            return [
                'image_prompt' => $style.' '.$prompt,
                'image' => null,
            ];
        }

        // Save the image to S3 and get CloudFront URL
        $s3ImageUrl = $this->saveImageToS3($imageResponse['url'], 'chapters', $chapterId);

        return [
            'image_prompt' => $imagePromptCompletion,
            'image' => $s3ImageUrl,
        ];
    }

    public function getBodyWordCount(string $bookId): int
    {
        $book = $this->bookService->getById($bookId, ['type', 'age_level']);

        $length = 2000;

        if ($book->age_level <= 18) {
            $length = 2000;
        }

        if ($book->age_level <= 13) {
            $length = 1000;
        }

        if ($book->age_level <= 9) {
            $length = 800;
        }

        if ($book->age_level <= 4) {
            $length = 400;
        }

        if ($book->age_level <= 2) {
            $length = 200;
        }

        return $length;
    }

    protected function getPersonaPrompt(string $bookId)
    {
        $book = $this->bookService->getById($bookId, ['id', 'plot', 'genre', 'age_level', 'user_characters', 'additional_instructions', 'scene', 'type']);

        $ageGroup = 'adult';

        if ($book->age_level <= 18) {
            $ageGroup = 'teenage';
        }

        if ($book->age_level <= 10) {
            $ageGroup = 'children';
        }

        if ($book->age_level <= 4) {
            $ageGroup = 'toddler';
        }

        return 'You are an author who is writing a fictitious '.$ageGroup.' '.strtolower($book->genre).'\'s '.$book->type.' book for '.$book->age_level.' year olds. ';
    }

    protected function getSystemPrompt(string $bookId)
    {
        $book = $this->bookService->getById($bookId, ['id', 'plot', 'genre', 'age_level', 'user_characters', 'additional_instructions', 'scene', 'type']);

        $this->chatService->resetMessages();

        $prompt = $this->getPersonaPrompt($bookId);
        $prompt .= 'Be as descriptive as possible. ';

        $rules = [
            'The story is about '.$book->plot,
            'Write in the third person',
            'The genre is '.$book->genre,
        ];

        if ($book->type == 'chapter') {
            $rules[] = 'As a chapter book the story will not resolve until the end';
            $rules[] = 'Each chapter should end creating excitement for more '.strtolower($book->genre).' in the next chapter';
        }

        if ($book->type == 'screenplay') {
            $rules[] = 'As a screenplay the story will not resolve until the end';
            $rules[] = 'Lines should be written with the name of the character and their line.';
        }

        if ($book->type == 'theatre') {
            $rules[] = 'As a theatre play the script will not resolve until the end.';
            $rules[] = 'Each line in the script should be written with the name of the character and their line.';
        }

        if ($book->scene) {
            $rules[] = 'The scene is '.$book->scene;
        }

        if ($book->additional_instructions) {
            $rules[] = $book->additional_instructions;
        }

        if ($book->user_characters) {
            $rules[] = 'The main characters are: '.$book->user_characters;
        }

        $prompt .= 'Your rules are: ';

        foreach ($rules as $i => $rule) {
            $prompt .= 'Rule #'.$i.': '.$rule.' |; ';
        }

        $prompt = rtrim($prompt, '; ');
        $prompt .= '. ';

        return $prompt;
    }

    /**
     * @param  array  $data
     */
    protected function setBackstory(string $bookId)
    {
        $this->chatService->resetMessages();
        $this->chatService->setContext($this->getSystemPrompt($bookId));
    }

    protected function soFar(string $bookId)
    {
        $book = $this->bookService->getById($bookId, ['id', 'plot', 'genre', 'age_level', 'user_characters', 'scene', 'type'], ['with' => ['chapters']]);

        if ($book->chapters->where('status', 'complete')->count() > 1) {
            if ($book->chapters->where('status', 'complete')->last()->book_summary) {
                $this->chatService->addUserMessage('What has happened so far?');
                $this->chatService->addAssistantMessage('So far '.$book->chapters->where('status', 'complete')->last()->book_summary);
            }
        }
    }

    /**
     * @param  array  $data
     */
    public function addCharacterProfiles(string $bookId)
    {
        if ($characterPrompt = $this->getCharacterProfilePrompt($bookId)) {
            $this->chatService->addAssistantMessage('Who are the characters?');
            $this->chatService->addUserMessage($characterPrompt);
        }
    }

    public function getCharacterProfilePrompt(string $bookId): ?string
    {
        $book = $this->bookService->getById($bookId, ['id', 'user_characters'], ['with' => ['chapters', 'chapters.characters', 'characters']]);
        $characterString = '';
        $lastChapter = $book->chapters->where('status', 'complete')->last();

        foreach ($book->characters as $character) {
            $characterString .= $character->name.': gender '.$character->gender.', age '.$character->age.', '.$character->description;

            if ($chapterCharacter = $lastChapter->characters->where('id', $character->id)->first()) {
                if ($chapterCharacter->pivot->experience) {
                    $characterString .= ' || has expererienced: '.$chapterCharacter->pivot->experience;
                }

                if ($chapterCharacter->pivot->inner_thoughts) {
                    $characterString .= ' || is thinking about: '.$chapterCharacter->pivot->inner_thoughts;
                }

                if ($chapterCharacter->pivot->personal_motivations) {
                    $characterString .= ' || is motivated by: '.$chapterCharacter->pivot->personal_motivations;
                }

                if ($chapterCharacter->pivot->goals) {
                    $characterString .= ' || goals are: '.$chapterCharacter->pivot->goals;
                }
            }

            $characterString .= '\n';
        }

        if ($characterString == '') {
            $characterString = $book->user_characters;
        }

        return $characterString;
    }

    public function lastChapter(string $bookId)
    {
        $book = $this->bookService->getById($bookId, ['id', 'plot', 'genre', 'age_level', 'user_characters', 'scene', 'type'], ['with' => ['chapters']]);
        if ($book->chapters->where('status', 'complete')->count() > 0) {

            if ($book->chapters->where('status', 'complete')->last()->summary) {
                $this->chatService->addUserMessage('What happened in the previous chapter?');
                $this->chatService->addAssistantMessage('In the previous chapter: '.$book->chapters->where('status', 'complete')->last()->summary);

                $lastBody = $book->chapters->where('status', 'complete')->last()->body;
                $paragraphs = explode(PHP_EOL, $lastBody);
                $possibleParagraphs = [];
                foreach ($paragraphs as $paragraph) {
                    if ($paragraph != '' && strlen($paragraph) > 20) {
                        $possibleParagraphs[] = $paragraph;
                    }
                }

                if (count($possibleParagraphs) >= 1) {
                    if (count($possibleParagraphs) >= 2) {
                        $lastParagraph = $possibleParagraphs[count($possibleParagraphs) - 2].' '.$possibleParagraphs[count($possibleParagraphs) - 1];
                    }

                    $lastParagraph = $possibleParagraphs[count($possibleParagraphs) - 1];

                    $this->chatService->addUserMessage('How did the last chapter end?');
                    $this->chatService->addAssistantMessage($lastParagraph);
                }
            }
        }
    }

    /**
     * @param  string  $msesage
     */
    public function stripQuotes(?string $message = null): ?string
    {
        if (! $message) {
            return null;
        }

        if (str_starts_with($message, '"')) {
            $message = ltrim($message, '"');
        }

        if (str_ends_with($message, '"')) {
            $message = rtrim($message, '"');
        }

        return $message;
    }

    /**
     * Parse JSON response, handling various formats that AI models may return.
     * Handles: direct JSON, markdown code blocks, JSON embedded in text, malformed JSON.
     *
     * @return array<string, mixed>|null
     */
    protected function parseJsonResponse(string $content): ?array
    {
        $content = trim($content);

        if ($content === '') {
            Log::warning('[BuilderService::parseJsonResponse] Empty content received');

            return null;
        }

        Log::debug('[BuilderService::parseJsonResponse] Starting parse', [
            'content_length' => strlen($content),
            'first_50_chars' => substr($content, 0, 50),
            'last_50_chars' => substr($content, -50),
        ]);

        $result = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($result)) {
            Log::debug('[BuilderService::parseJsonResponse] Direct JSON decode succeeded');

            return $result;
        }

        $directError = json_last_error_msg();
        Log::debug('[BuilderService::parseJsonResponse] Direct decode failed', ['error' => $directError]);

        $extracted = $this->extractJsonFromMarkdown($content);
        if ($extracted !== null) {
            Log::debug('[BuilderService::parseJsonResponse] Extracted from markdown', ['length' => strlen($extracted)]);

            $result = $this->tryParseJson($extracted, 'markdown');
            if ($result !== null) {
                return $result;
            }
        }

        $extracted = $this->extractJsonObject($content);
        if ($extracted !== null) {
            Log::debug('[BuilderService::parseJsonResponse] Extracted JSON object', ['length' => strlen($extracted)]);

            $result = $this->tryParseJson($extracted, 'extracted');
            if ($result !== null) {
                return $result;
            }
        }

        $result = $this->tryParseJson($content, 'original');
        if ($result !== null) {
            return $result;
        }

        Log::info('[BuilderService::parseJsonResponse] All JSON strategies failed, attempting regex extraction');
        $result = $this->extractFieldsWithRegex($content);
        if ($result !== null && ! empty($result)) {
            Log::info('[BuilderService::parseJsonResponse] Regex extraction succeeded', [
                'fields' => array_keys($result),
            ]);

            return $result;
        }

        Log::error('[BuilderService::parseJsonResponse] All parsing attempts failed', [
            'original_length' => strlen($content),
            'content_preview' => substr($content, 0, 500),
            'content_end' => substr($content, -200),
        ]);

        return null;
    }

    /**
     * Try multiple strategies to parse JSON string.
     *
     * @return array<string, mixed>|null
     */
    protected function tryParseJson(string $json, string $source): ?array
    {
        $result = json_decode($json, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($result)) {
            Log::debug("[BuilderService::tryParseJson] Direct decode succeeded for {$source}");

            return $result;
        }

        $error1 = json_last_error_msg();

        $sanitized = $this->sanitizeJsonString($json);
        $result = json_decode($sanitized, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($result)) {
            Log::debug("[BuilderService::tryParseJson] Sanitized decode succeeded for {$source}");

            return $result;
        }

        $error2 = json_last_error_msg();

        $repaired = $this->repairMalformedJson($json);
        $result = json_decode($repaired, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($result)) {
            Log::debug("[BuilderService::tryParseJson] Repaired decode succeeded for {$source}");

            return $result;
        }

        $error3 = json_last_error_msg();

        Log::debug("[BuilderService::tryParseJson] All strategies failed for {$source}", [
            'direct_error' => $error1,
            'sanitized_error' => $error2,
            'repaired_error' => $error3,
        ]);

        return null;
    }

    /**
     * Repair common JSON malformations from LLM outputs.
     */
    protected function repairMalformedJson(string $json): string
    {
        $json = preg_replace('/,\s*}/', '}', $json);
        $json = preg_replace('/,\s*]/', ']', $json);

        $repaired = '';
        $inString = false;
        $escape = false;
        $length = strlen($json);

        for ($i = 0; $i < $length; $i++) {
            $char = $json[$i];
            $ord = ord($char);

            if ($escape) {
                if ($char === 'n' || $char === 'r' || $char === 't' || $char === '"' || $char === '\\' || $char === '/' || $char === 'b' || $char === 'f' || $char === 'u') {
                    $repaired .= $char;
                } else {
                    $repaired .= '\\';
                    $repaired .= $char;
                }
                $escape = false;

                continue;
            }

            if ($char === '\\') {
                $repaired .= $char;
                $escape = true;

                continue;
            }

            if ($char === '"') {
                $inString = ! $inString;
                $repaired .= $char;

                continue;
            }

            if ($inString) {
                if ($ord < 32) {
                    switch ($ord) {
                        case 10:
                            $repaired .= '\\n';
                            break;
                        case 13:
                            $repaired .= '\\r';
                            break;
                        case 9:
                            $repaired .= '\\t';
                            break;
                        default:
                            $repaired .= sprintf('\\u%04x', $ord);
                            break;
                    }

                    continue;
                }
            }

            $repaired .= $char;
        }

        return $repaired;
    }

    /**
     * Extract chapter fields using regex as a last resort.
     *
     * @return array<string, string>|null
     */
    protected function extractFieldsWithRegex(string $content): ?array
    {
        $result = [];

        if (preg_match('/"body"\s*:\s*"((?:[^"\\\\]|\\\\.|"(?=[^:},\s]*[},]))*?)"\s*[,}]/s', $content, $match)) {
            $result['body'] = $this->unescapeJsonString($match[1]);
        }

        if (empty($result['body'])) {
            if (preg_match('/"body"\s*:\s*"(.+?)(?:"\s*,\s*"(?:title|summary|image_prompt)")/s', $content, $match)) {
                $result['body'] = $this->unescapeJsonString($match[1]);
            }
        }

        if (preg_match('/"title"\s*:\s*"([^"]*(?:\\\\.[^"]*)*)"/s', $content, $match)) {
            $result['title'] = $this->unescapeJsonString($match[1]);
        }

        if (preg_match('/"summary"\s*:\s*"([^"]*(?:\\\\.[^"]*)*)"/s', $content, $match)) {
            $result['summary'] = $this->unescapeJsonString($match[1]);
        }

        if (preg_match('/"image_prompt"\s*:\s*"([^"]*(?:\\\\.[^"]*)*)"/s', $content, $match)) {
            $result['image_prompt'] = $this->unescapeJsonString($match[1]);
        }

        if (empty($result)) {
            return null;
        }

        Log::debug('[BuilderService::extractFieldsWithRegex] Extracted fields', [
            'has_body' => isset($result['body']),
            'body_length' => strlen($result['body'] ?? ''),
            'has_title' => isset($result['title']),
            'has_summary' => isset($result['summary']),
            'has_image_prompt' => isset($result['image_prompt']),
        ]);

        return $result;
    }

    /**
     * Unescape a JSON string value.
     */
    protected function unescapeJsonString(string $value): string
    {
        $value = str_replace('\\n', "\n", $value);
        $value = str_replace('\\r', "\r", $value);
        $value = str_replace('\\t', "\t", $value);
        $value = str_replace('\\"', '"', $value);
        $value = str_replace('\\\\', '\\', $value);

        return $value;
    }

    /**
     * Extract JSON from markdown code blocks.
     */
    protected function extractJsonFromMarkdown(string $content): ?string
    {
        if (preg_match('/```(?:json)?\s*\n?(.*?)\n?```/s', $content, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    /**
     * Extract a JSON object or array from text that may contain surrounding content.
     */
    protected function extractJsonObject(string $content): ?string
    {
        $firstBrace = strpos($content, '{');
        $firstBracket = strpos($content, '[');

        if ($firstBrace === false && $firstBracket === false) {
            return null;
        }

        if ($firstBrace !== false && ($firstBracket === false || $firstBrace < $firstBracket)) {
            $startChar = '{';
            $endChar = '}';
            $startPos = $firstBrace;
        } else {
            $startChar = '[';
            $endChar = ']';
            $startPos = $firstBracket;
        }

        $depth = 0;
        $inString = false;
        $escape = false;
        $length = strlen($content);

        for ($i = $startPos; $i < $length; $i++) {
            $char = $content[$i];

            if ($escape) {
                $escape = false;

                continue;
            }

            if ($char === '\\') {
                $escape = true;

                continue;
            }

            if ($char === '"') {
                $inString = ! $inString;

                continue;
            }

            if (! $inString) {
                if ($char === $startChar) {
                    $depth++;
                } elseif ($char === $endChar) {
                    $depth--;

                    if ($depth === 0) {
                        return substr($content, $startPos, $i - $startPos + 1);
                    }
                }
            }
        }

        if ($depth > 0) {
            Log::warning('[BuilderService::extractJsonObject] Unclosed JSON structure', [
                'depth' => $depth,
                'start_char' => $startChar,
            ]);
        }

        return null;
    }

    /**
     * Sanitize a JSON string by escaping control characters inside string values.
     */
    protected function sanitizeJsonString(string $json): string
    {
        $result = '';
        $inString = false;
        $escape = false;
        $length = strlen($json);

        for ($i = 0; $i < $length; $i++) {
            $char = $json[$i];
            $ord = ord($char);

            if ($escape) {
                $result .= $char;
                $escape = false;

                continue;
            }

            if ($char === '\\') {
                $result .= $char;
                $escape = true;

                continue;
            }

            if ($char === '"') {
                $inString = ! $inString;
                $result .= $char;

                continue;
            }

            if ($inString && $ord < 32) {
                switch ($ord) {
                    case 10:
                        $result .= '\\n';
                        break;
                    case 13:
                        $result .= '\\r';
                        break;
                    case 9:
                        $result .= '\\t';
                        break;
                    default:
                        $result .= sprintf('\\u%04x', $ord);
                        break;
                }

                continue;
            }

            $result .= $char;
        }

        return $result;
    }
}
