<?php

namespace App\Services\StabilityAI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class StabilityAIService
{
    protected $apiKey;
    protected $endpoint;

    public function __construct()
    {
        $this->apiKey = config('stabilityai.api_key');
        $this->endpoint = 'https://api.stability.ai/v2beta/stable-image/generate/ultra';
    }

    public function generateImage(string $prompt, string $aspectRatio = '1:1', string $basePrompts = '', string $endPrompts = '', string $negative = '', string $existingImagePath = null)
    {
        $requestId = uniqid('req_');
        Log::debug("[$requestId] Starting image generation", [
            'prompt' => $prompt,
            'aspectRatio' => $aspectRatio,
            'existingImagePath' => $existingImagePath
        ]);
        
        $prompt = $basePrompts . $prompt . ' ' . $endPrompts;

        $settings = [
            [
                'name' => 'none',
                'contents' => ''
            ],
            [
                'name' => 'prompt',
                'contents' => $prompt
            ],
            [
                'name' => 'negative_prompt',
                'contents' => $negative . 'malformed, missing limbs, topless, bare shoulders, nude, posed, dead eyes, scary eyes, vibrant eyes, fluorescent iris, flat background, airbrushhed, flash photography, full lighting.'
            ],
            [
                'name' => 'output_format',
                'contents' => 'jpeg'
            ],
            [
                'name' => 'aspect_ratio',
                'contents' => $aspectRatio
            ]
        ];

        Log::debug("[$requestId] Existing image path", ['path' => $existingImagePath]);

        if(!empty($existingImagePath)) {
            $filePath = storage_path('app/public/' . $existingImagePath);
            Log::debug("[$requestId] Checking file path", ['path' => $filePath, 'file_exists' => file_exists($filePath)]);

            if(file_exists($filePath)) {
                Log::debug("[$requestId] File exists, adding image-to-image mode settings");
                
                $settings[] = [
                    'name' => 'mode',
                    'contents' => 'image-to-image'
                ];

                $settings[] = [
                    'name' => 'strength',
                    'contents' => .2
                ];

                try {
                    $fileContents = file_get_contents($filePath);
                    Log::debug("[$requestId] File contents retrieved", ['size' => strlen($fileContents)]);
                    
                    $settings[] = [
                        'name' => 'image',
                        'contents' => $fileContents
                    ];
                } catch (\Exception $e) {
                    Log::error("[$requestId] Failed to get file contents", ['error' => $e->getMessage()]);
                }
            } else {
                Log::warning("[$requestId] Existing image path provided but file not found", ['path' => $filePath]);
            }
        }

        Log::debug("[$requestId] Final settings structure", ['count' => count($settings)]);
        
        try {
            Log::debug("[$requestId] Making API request to Stability AI");
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Accept' => 'image/*',
            ])->asMultipart()->post($this->endpoint, $settings);
            
            if ($response->successful()) {
                Log::debug("[$requestId] API request successful", ['status' => $response->status()]);
                return $response->body();
            } else {
                Log::error("[$requestId] API request failed", [
                    'status' => $response->status(),
                    'body' => (string)$response->body()
                ]);
                throw new \Exception(json_encode($response->body()));
            }
        } catch (\Exception $e) {
            Log::error("[$requestId] Exception during API request", [
                'message' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * 
     */
    public function getImage(string $prompt, string $size = '1:1', string $basePrompts = '', string $endPrompts = '', string $negative = '', string $existingImagePath = null)
    {
        $image = $this->generateImage($prompt, $size, $basePrompts, $endPrompts, $negative, $existingImagePath);
        return $this->saveImage($image);
    }

    /**
     * 
     */
    public function saveImage($contents)
    {
        // if(!$contents = file_get_contents($imageUrl)) {
        //     Log::debug('image was not available in file_get_contents');
        //     return;
        // }

        // $imageName = basename($imageUrl);
        $imageName = time() . '.png';
        return $this->copyImageToS3($imageName, $contents);
    }

    /**
     * 
     */
    protected function copyImageToS3(string $imageName, $contents):string|null
    {
        if(Storage::disk('local')->put('public/' . $imageName, $contents)) {
            return '/storage/' . $imageName;
        } else  {
            Log::debug('image was not placed');
            return null;
        }
    }
}
