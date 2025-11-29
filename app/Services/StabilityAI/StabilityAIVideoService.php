<?php

namespace App\Services\StabilityAI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Intervention\Image\ImageManager;

class StabilityAIVideoService
{
    protected $apiKey;
    protected $endpoint;
    protected $resultEndpoint;

    public function __construct()
    {
        $this->apiKey = config('stabilityai.api_key');
        $this->endpoint = 'https://api.stability.ai/v2beta/image-to-video';
        $this->resultEndpoint = 'https://api.stability.ai/v2beta/image-to-video/result/';
    }

    public function generateVideo($imagePath, $seed = 0, $cfgScale = 1.8, $motionBucketId = 127)
    {
        // dd($imagePath);
        // Remove 'app/' part and create a temp resized image

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Accept' => 'video/*',
        ])->asMultipart()->post($this->endpoint, [
            [
                'name' => 'image',
                'contents' => fopen($imagePath, 'r')
            ],
            [
                'name' => 'seed',
                'contents' => $seed
            ],
            [
                'name' => 'cfg_scale',
                'contents' => $cfgScale
            ],
            [
                'name' => 'motion_bucket_id',
                'contents' => $motionBucketId
            ]
        ]);

        unlink($imagePath);

        if ($response->successful()) {
            return $response->body();
        } else {
            Log::debug('API Error:', ['response' => $response->body()]);
            throw new \Exception('Failed to generate video. Check the logs for more details.');
        }
    }

    public function retrieveVideo($generationId)
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Accept' => 'video/*'
        ])->get($this->resultEndpoint . $generationId);

        if ($response->status() == 202) {
            Log::debug('Generation in-progress, try again in 10 seconds.');
            return null; // Indicate that the generation is still in progress
        } elseif ($response->status() == 200) {
            return $response->body();
        } else {
            Log::debug('API Error:', ['response' => $response->body()]);
            throw new \Exception('Failed to retrieve video. Check the logs for more details.');
        }
    }

    public function createTempResizedImage($imagePath)
    {
        $image = ImageManager::imagick()->read($imagePath);
        $image->resize(768, 768);
        
        $uniqueTempPath = storage_path('app/temp_resized_image_' . uniqid() . '.png');
        $image->save($uniqueTempPath);

        return $uniqueTempPath;
    }
}