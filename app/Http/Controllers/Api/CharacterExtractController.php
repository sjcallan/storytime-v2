<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Ai\AiManager;
use App\Services\Ai\Contracts\AiChatServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CharacterExtractController extends Controller
{
    protected AiChatServiceInterface $chatService;

    public function __construct(AiManager $aiManager)
    {
        $this->chatService = $aiManager->chat();
    }

    /**
     * Extract characters from a plot description.
     */
    public function extract(Request $request): JsonResponse
    {
        Log::info('CharacterExtract: Request received');

        try {
            $validated = $request->validate([
                'plot' => ['required', 'string', 'max:5000'],
                'genre' => ['nullable', 'string', 'max:255'],
                'age_level' => ['nullable', 'integer', 'min:4', 'max:18'],
            ]);

            Log::info('CharacterExtract: Validation passed', $validated);

            $plot = $validated['plot'];
            $genre = $validated['genre'] ?? '';
            $ageLevel = $validated['age_level'] ?? 10;

            $ageGroup = match (true) {
                $ageLevel <= 4 => 'toddler',
                $ageLevel <= 10 => 'children',
                $ageLevel <= 18 => 'teenage',
                default => 'adult',
            };

            $characterTemplate = [
                'characters' => [
                    [
                        'name' => 'Character name',
                        'age' => 'Age as a number or description',
                        'gender' => 'male, female, or non-binary',
                        'description' => 'Physical appearance and personality traits',
                        'backstory' => '3-4 sentence background history of this charcter',
                    ],
                ],
            ];

            $systemPrompt = "You are a creative writing assistant helping to create characters for a {$ageGroup} {$genre} story. ";
            $systemPrompt .= 'Based on the story plot provided, identify or create the main characters that would be in this story. ';
            $systemPrompt .= 'Identify the characters named in the plot or create 2-4 interesting characters that fit the story. ';
            $systemPrompt .= 'Respond ONLY with valid JSON in this exact format: '.json_encode($characterTemplate);

            $userMessage = "Here is the story plot:\n\n{$plot}\n\Indentify the characters from this story.";

            Log::info('CharacterExtract: Making AI request');

            $this->chatService->resetMessages();
            $this->chatService->setResponseFormat('json_object');
            $this->chatService->addSystemMessage($systemPrompt);
            $this->chatService->addUserMessage($userMessage);

            $result = $this->chatService->chat();

            if (empty($result['completion'])) {
                Log::error('CharacterExtract: AI request failed', [
                    'error' => $result['error'] ?? 'Empty completion',
                ]);

                return response()->json(['characters' => [], 'error' => 'Failed to generate characters']);
            }

            Log::info('CharacterExtract: Parsing response', ['completion' => $result['completion']]);

            $parsed = $this->parseJsonResponse($result['completion']);

            if (! $parsed || ! isset($parsed['characters'])) {
                Log::error('CharacterExtract: Failed to parse', ['completion' => $result['completion']]);

                return response()->json(['characters' => []]);
            }

            Log::info('CharacterExtract: Success', ['count' => count($parsed['characters'])]);

            return response()->json($parsed);
        } catch (\Exception $e) {
            Log::error('CharacterExtract: Exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['characters' => [], 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Parse JSON response, handling control characters that may be present in AI responses.
     *
     * @return array<string, mixed>|null
     */
    protected function parseJsonResponse(string $content): ?array
    {
        $result = json_decode($content, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            return $result;
        }

        $sanitized = $this->sanitizeJsonString($content);
        $result = json_decode($sanitized, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            return $result;
        }

        Log::warning('CharacterExtract: JSON parse failed after sanitization', [
            'error' => json_last_error_msg(),
        ]);

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
