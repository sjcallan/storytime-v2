<?php

namespace App\Traits\Service;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait SavesImagesToS3
{
    /**
     * The CloudFront distribution URL for serving images.
     */
    protected function getCloudFrontUrl(): string
    {
        return 'https://d3lz6w5lgn41k.cloudfront.net';
    }

    /**
     * Download an image from a URL, save locally first, then upload to S3.
     *
     * @param  string  $imageUrl  The remote image URL to download
     * @param  string  $directory  The directory within 'images/' (e.g., 'portraits', 'covers', 'chapters')
     * @param  string  $identifier  A unique identifier for the file (e.g., characterId, bookId, chapterId)
     * @param  string  $extension  The file extension (default: 'webp')
     * @return string|null The CloudFront URL or null on failure
     */
    protected function saveImageToS3(
        string $imageUrl,
        string $directory,
        string $identifier,
        string $extension = 'webp'
    ): ?string {
        $localPath = null;

        try {
            $response = Http::timeout(60)->get($imageUrl);

            if ($response->failed()) {
                Log::error('Failed to download image for S3 upload', [
                    'url' => $imageUrl,
                    'status' => $response->status(),
                ]);

                return null;
            }

            $filename = "images/{$identifier}_".Str::random(8).".{$extension}";
            $localPath = "temp/{$identifier}_".Str::random(8).".{$extension}";

            // Save to local storage first
            Storage::disk('local')->put($localPath, $response->body());

            // Upload to S3 from local file
            $localFilePath = Storage::disk('local')->path($localPath);
            $fileContents = file_get_contents($localFilePath);

            $uploaded = Storage::disk('s3')->put($filename, $fileContents);

            if (! $uploaded) {
                Log::error('S3 upload returned false', [
                    'filename' => $filename,
                    'file_size' => strlen($fileContents),
                ]);
                Storage::disk('local')->delete($localPath);

                return null;
            }

            // Verify the file actually exists on S3
            if (! Storage::disk('s3')->exists($filename)) {
                Log::error('S3 file does not exist after upload', [
                    'filename' => $filename,
                ]);
                Storage::disk('local')->delete($localPath);

                return null;
            }

            // Clean up local temp file
            Storage::disk('local')->delete($localPath);

            $cloudFrontUrl = $this->getCloudFrontUrl().'/'.str_replace('images/', '', $filename);

            Log::info('Image saved to S3 (verified)', [
                'filename' => $filename,
                'cloudfront_url' => $cloudFrontUrl,
                'file_size' => Storage::disk('s3')->size($filename),
            ]);

            return $cloudFrontUrl;
        } catch (\Exception $e) {
            Log::error('Error saving image to S3', [
                'error' => $e->getMessage(),
                'url' => $imageUrl,
            ]);

            // Clean up local temp file if it exists
            if ($localPath && Storage::disk('local')->exists($localPath)) {
                Storage::disk('local')->delete($localPath);
            }

            return null;
        }
    }

    /**
     * Get the full CloudFront URL for a stored image path.
     *
     * @param  string|null  $imagePath  The stored image path
     * @return string|null The CloudFront URL or null if path is empty
     */
    protected function getCloudFrontImageUrl(?string $imagePath): ?string
    {
        if (empty($imagePath)) {
            return null;
        }

        // If it's already a CloudFront URL, return as-is
        if (str_starts_with($imagePath, 'https://d3lz6w5lgn41k.cloudfront.net')) {
            return $imagePath;
        }

        // If it's a legacy local storage path, convert it
        if (str_starts_with($imagePath, '/storage/')) {
            $relativePath = str_replace('/storage/', 'images/', $imagePath);

            return $this->getCloudFrontUrl().'/'.$relativePath;
        }

        // If it's just a filename or relative path, prepend CloudFront URL
        return $this->getCloudFrontUrl().'/'.$imagePath;
    }
}
