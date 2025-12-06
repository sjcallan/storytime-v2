<?php

namespace App\Console\Commands;

use App\Services\Ai\Llama\ApiService;
use App\Services\Ai\Llama\ChatService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestLlamaApiCommand extends Command
{
    protected $signature = 'llama:test 
                            {--prompt= : Custom prompt to send}
                            {--raw : Show raw HTTP response before normalization}
                            {--json : Request JSON format response}
                            {--completion : Use completion mode instead of chat}
                            {--chapter-test : Test JSON response like chapter builder does}';

    protected $description = 'Test the Llama API service and debug response format issues';

    public function handle(): int
    {
        $this->info('🦙 Llama API Service Test');
        $this->newLine();

        $this->displayConfiguration();

        if (! $this->testConnection()) {
            return Command::FAILURE;
        }

        $this->newLine();
        $this->info('📡 Sending test request...');
        $this->newLine();

        if ($this->option('chapter-test')) {
            $this->sendChapterTestRequest();
        } elseif ($this->option('raw')) {
            $this->sendRawRequest();
        } else {
            $this->sendServiceRequest();
        }

        return Command::SUCCESS;
    }

    protected function displayConfiguration(): void
    {
        $this->info('📋 Current Configuration:');
        $this->table(
            ['Setting', 'Value'],
            [
                ['Base URL', config('ai.providers.llama.base_url', 'http://127.0.0.1:5009')],
                ['Endpoint', config('ai.providers.llama.endpoint', '/generate')],
                ['Model', config('ai.providers.llama.model', 'llama-3.2')],
                ['Max Tokens', config('ai.providers.llama.max_tokens', 4000)],
                ['Temperature', config('ai.providers.llama.temperature', 0.8)],
                ['Timeout', config('ai.providers.llama.timeout', 300).'s'],
            ]
        );
    }

    protected function testConnection(): bool
    {
        $baseUrl = config('ai.providers.llama.base_url', 'http://127.0.0.1:5009');

        $this->info('🔌 Testing connection to: '.$baseUrl);

        try {
            $response = Http::timeout(5)->get($baseUrl);
            $this->info('✅ Connection successful (Status: '.$response->status().')');

            return true;
        } catch (\Exception $e) {
            $this->error('❌ Connection failed: '.$e->getMessage());
            $this->newLine();
            $this->warn('Make sure your Llama server is running at: '.$baseUrl);

            return false;
        }
    }

    protected function sendRawRequest(): void
    {
        $this->warn('🔍 RAW MODE: Showing unprocessed HTTP response');
        $this->newLine();

        $baseUrl = config('ai.providers.llama.base_url', 'http://127.0.0.1:5009');
        $endpoint = config('ai.providers.llama.endpoint', '/generate');
        $url = rtrim($baseUrl, '/').'/'.ltrim($endpoint, '/');

        $prompt = $this->option('prompt') ?? 'Tell me a very short joke in one sentence.';

        $settings = [
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
            'max_tokens' => config('ai.providers.llama.max_tokens', 4000),
            'temperature' => config('ai.providers.llama.temperature', 0.8),
            'model' => config('ai.providers.llama.model', 'llama-3.2'),
        ];

        if ($this->option('json')) {
            $settings['response_format'] = ['type' => 'json_object'];
        }

        $this->info('📤 Request URL: '.$url);
        $this->newLine();

        $this->info('📤 Request Payload:');
        $this->line(json_encode($settings, JSON_PRETTY_PRINT));
        $this->newLine();

        try {
            $startTime = microtime(true);
            $httpResponse = Http::timeout(60)->post($url, $settings);
            $endTime = microtime(true);

            $this->info('⏱️  Response Time: '.round($endTime - $startTime, 2).'s');
            $this->info('📊 HTTP Status: '.$httpResponse->status());
            $this->newLine();

            $this->info('📥 Raw Response Headers:');
            $headers = $httpResponse->headers();
            foreach (['content-type', 'content-length', 'transfer-encoding'] as $header) {
                if (isset($headers[$header])) {
                    $this->line("   {$header}: ".implode(', ', $headers[$header]));
                }
            }
            $this->newLine();

            $this->info('📥 Raw Response Body (string):');
            $body = $httpResponse->body();
            $this->line('   Length: '.strlen($body).' bytes');
            $this->line('   Type: '.gettype($body));
            $this->newLine();
            $this->line($body);
            $this->newLine();

            $this->info('📥 Response as JSON (parsed):');
            $jsonResponse = $httpResponse->json();

            if ($jsonResponse === null) {
                $this->warn('   ⚠️  JSON parsing returned null');
                $this->warn('   Last JSON error: '.json_last_error_msg());
            } else {
                $this->line('   Type: '.gettype($jsonResponse));
                $this->line('   Keys: '.(is_array($jsonResponse) ? implode(', ', array_keys($jsonResponse)) : 'N/A'));
                $this->newLine();
                $this->line(json_encode($jsonResponse, JSON_PRETTY_PRINT));
            }

            $this->newLine();
            $this->analyzeResponseFormat($jsonResponse, $body);
        } catch (\Exception $e) {
            $this->error('❌ Request failed: '.$e->getMessage());
        }
    }

    protected function sendServiceRequest(): void
    {
        $this->info('🔧 SERVICE MODE: Using ApiService class');
        $this->newLine();

        $service = new ApiService;

        $prompt = $this->option('prompt') ?? 'Tell me a very short joke in one sentence.';

        if ($this->option('json')) {
            $service->setResponseFormat('json_object');
        }

        $this->info('📤 Prompt: "'.$prompt.'"');
        $this->newLine();

        if ($this->option('completion')) {
            $this->info('Using completion() method...');
            $response = $service->completion($prompt);
        } else {
            $this->info('Using chat() method...');
            $response = $service->chat([
                ['role' => 'user', 'content' => $prompt],
            ]);
        }

        $this->newLine();
        $this->info('📊 Service Response:');

        if ($response === null) {
            $this->error('❌ Response is NULL');
            $this->error('   Error: '.($service->getError() ?? 'Unknown error'));
            $this->error('   Status Code: '.($service->getResponseStatusCode() ?? 'N/A'));

            $this->newLine();
            $this->warn('💡 Try running with --raw flag to see the actual HTTP response');

            return;
        }

        $this->info('✅ Response received');
        $this->line('   Status Code: '.$service->getResponseStatusCode());
        $this->line('   Response Time: '.round($service->getResponseTime(), 2).'s');
        $this->newLine();

        $this->info('📥 Normalized Response Structure:');
        $this->displayResponseStructure($response);

        $this->newLine();
        $this->info('📥 Full Response:');
        $this->line(json_encode($response, JSON_PRETTY_PRINT));

        $this->newLine();
        $this->info('📝 Extracted Content:');
        $content = $response['choices'][0]['message']['content'] ?? $response['choices'][0]['text'] ?? 'NOT FOUND';
        $this->line($content);
    }

    protected function displayResponseStructure(array $response, string $prefix = ''): void
    {
        foreach ($response as $key => $value) {
            $type = gettype($value);
            if (is_array($value)) {
                $this->line("{$prefix}[{$key}] => array(".count($value).')');
                if (count($value) <= 5) {
                    $this->displayResponseStructure($value, $prefix.'   ');
                }
            } elseif (is_string($value)) {
                $display = strlen($value) > 50 ? substr($value, 0, 50).'...' : $value;
                $this->line("{$prefix}[{$key}] => string(".strlen($value).') "'.$display.'"');
            } else {
                $this->line("{$prefix}[{$key}] => {$type}: ".json_encode($value));
            }
        }
    }

    protected function analyzeResponseFormat(?array $jsonResponse, string $rawBody): void
    {
        $this->info('🔬 Response Format Analysis:');
        $this->newLine();

        $expectedKeys = ['response', 'generated_text', 'text', 'choices', 'content'];
        $foundKeys = [];

        if ($jsonResponse) {
            foreach ($expectedKeys as $key) {
                if (isset($jsonResponse[$key])) {
                    $foundKeys[] = $key;
                }
            }
        }

        if (empty($foundKeys)) {
            $this->warn('⚠️  None of the expected content keys found!');
            $this->line('   Expected one of: '.implode(', ', $expectedKeys));
            $this->newLine();

            if ($jsonResponse) {
                $this->line('   Actual keys: '.implode(', ', array_keys($jsonResponse)));
            }

            $this->newLine();
            $this->info('💡 Suggestions:');
            $this->line('   1. Check what key your Llama server uses for the response content');
            $this->line('   2. Update normalizeResponse() in ApiService.php to handle this format');
            $this->line('   3. The raw body content may be the response itself if not JSON');
        } else {
            $this->info('✅ Found content in key(s): '.implode(', ', $foundKeys));
        }
    }

    protected function sendChapterTestRequest(): void
    {
        $this->warn('📖 CHAPTER TEST MODE: Simulating chapter builder JSON request');
        $this->newLine();

        $chatService = app(ChatService::class);
        $chatService->resetMessages();
        $chatService->setResponseFormat('json_object');

        $systemPrompt = json_encode([
            'you_are' => 'An author writing a short story.',
            'rules' => [
                'Keep the response under 200 words',
                'Return valid JSON',
            ],
        ]);

        $chatService->addSystemMessage($systemPrompt);

        $outputFormat = [
            'body' => 'A short paragraph of story text',
            'title' => 'A chapter title',
            'summary' => 'A brief summary',
        ];

        $userPrompt = 'Write a very short opening paragraph for a mystery story. ';
        $userPrompt .= 'Respond with a JSON object containing: '.json_encode($outputFormat);

        $chatService->addUserMessage($userPrompt);

        $this->info('📤 System Prompt:');
        $this->line($systemPrompt);
        $this->newLine();

        $this->info('📤 User Prompt:');
        $this->line($userPrompt);
        $this->newLine();

        $this->info('⏳ Calling chat service...');
        $startTime = microtime(true);
        $result = $chatService->chat();
        $duration = microtime(true) - $startTime;

        $this->info('⏱️  Response Time: '.round($duration, 2).'s');
        $this->newLine();

        if (empty($result['completion'])) {
            $this->error('❌ Empty completion received');
            $this->line('Full result: '.json_encode($result, JSON_PRETTY_PRINT));

            return;
        }

        $this->info('📥 Raw Completion (from ChatService):');
        $this->line($result['completion']);
        $this->newLine();

        $this->info('🔧 Testing JSON decode...');
        $jsonData = json_decode($result['completion'], true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('❌ Direct JSON decode failed: '.json_last_error_msg());
            $this->newLine();

            $this->info('🔧 Testing sanitized JSON decode...');
            $sanitized = $this->sanitizeJsonString($result['completion']);

            $this->info('📥 Sanitized content:');
            $this->line($sanitized);
            $this->newLine();

            $jsonData = json_decode($sanitized, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->error('❌ Sanitized JSON decode also failed: '.json_last_error_msg());

                return;
            }

            $this->info('✅ Sanitized JSON decode succeeded!');
        } else {
            $this->info('✅ Direct JSON decode succeeded!');
        }

        $this->newLine();
        $this->info('📥 Parsed JSON Data:');
        $this->line(json_encode($jsonData, JSON_PRETTY_PRINT));

        $this->newLine();
        $this->info('📋 Extracted Fields:');
        $this->line('   body: '.($jsonData['body'] ?? 'NOT FOUND'));
        $this->line('   title: '.($jsonData['title'] ?? 'NOT FOUND'));
        $this->line('   summary: '.($jsonData['summary'] ?? 'NOT FOUND'));
    }

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
