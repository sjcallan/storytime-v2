<?php

namespace App\Listeners\Book;

use App\Events\Book\BookDeletedEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DeleteBookImagesListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The queue connection that should be used.
     */
    public string $queue = 'default';

    /**
     * Handle the event.
     *
     * Deletes all images associated with the book from S3 storage
     * and soft deletes the image records from the database.
     */
    public function handle(BookDeletedEvent $event): void
    {
        $book = $event->book;

        $images = $book->images()->withTrashed()->get();

        if ($images->isEmpty()) {
            return;
        }

        Log::info('Deleting images for book', [
            'book_id' => $book->id,
            'image_count' => $images->count(),
        ]);

        foreach ($images as $image) {
            $this->deleteImageFromS3($image->image_url);
            $image->delete();
        }

        Log::info('Completed deleting images for book', [
            'book_id' => $book->id,
        ]);
    }

    /**
     * Delete an image from S3 storage.
     */
    protected function deleteImageFromS3(?string $imageUrl): void
    {
        if (empty($imageUrl)) {
            return;
        }

        $s3Key = $this->extractS3Key($imageUrl);

        if (empty($s3Key)) {
            return;
        }

        try {
            if (Storage::disk('s3')->exists($s3Key)) {
                Storage::disk('s3')->delete($s3Key);

                Log::info('Deleted image from S3', [
                    'image_url' => $imageUrl,
                    's3_key' => $s3Key,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to delete image from S3', [
                'image_url' => $imageUrl,
                's3_key' => $s3Key,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Extract the S3 key from a CloudFront URL or relative path.
     *
     * @param  string  $imageUrl  The image URL (CloudFront URL or relative path)
     * @return string|null The S3 key (e.g., 'images/filename.webp')
     */
    protected function extractS3Key(string $imageUrl): ?string
    {
        $cloudFrontDomain = 'd3lz6w5lgn41k.cloudfront.net';

        if (str_contains($imageUrl, $cloudFrontDomain)) {
            $filename = basename(parse_url($imageUrl, PHP_URL_PATH));

            return 'images/'.$filename;
        }

        if (str_starts_with($imageUrl, 'http')) {
            return null;
        }

        if (str_starts_with($imageUrl, 'images/')) {
            return $imageUrl;
        }

        return 'images/'.$imageUrl;
    }
}
