<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CharacterExtractController extends Controller
{
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
                        'backstory' => 'Brief background story',
                    ],
                ],
            ];

            $systemPrompt = "You are a creative writing assistant helping to create characters for a {$ageGroup} {$genre} story. ";
            $systemPrompt .= 'Based on the story plot provided, identify or create the main characters that would be in this story. ';
            $systemPrompt .= 'Create 2-4 interesting characters that fit the story. ';
            $systemPrompt .= 'Respond ONLY with valid JSON in this exact format: '.json_encode($characterTemplate);

            $messages = [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => "Here is the story plot:\n\n{$plot}\n\nCreate the characters for this story."],
            ];

            Log::info('CharacterExtract: Making OpenAI request');

            $response = Http::withToken(config('services.openai.api_key'))
                ->timeout(60)
                ->post(config('services.openai.base_url', 'https://api.openai.com/v1').'/chat/completions', [
                    'model' => 'gpt-4o-mini',
                    'messages' => $messages,
                    'temperature' => 0.7,
                    'response_format' => ['type' => 'json_object'],
                ]);

            Log::info('CharacterExtract: Got response', ['status' => $response->status()]);

            if ($response->failed()) {
                Log::error('CharacterExtract: OpenAI request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return response()->json(['characters' => [], 'error' => 'Failed to generate characters']);
            }

            $data = $response->json();
            $completion = $data['choices'][0]['message']['content'] ?? '';

            Log::info('CharacterExtract: Parsing response', ['completion' => $completion]);

            $parsed = json_decode($completion, true);

            if (! $parsed || ! isset($parsed['characters'])) {
                Log::error('CharacterExtract: Failed to parse', ['completion' => $completion]);

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
}
