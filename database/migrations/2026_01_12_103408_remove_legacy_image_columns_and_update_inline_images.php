<?php

use App\Enums\ImageStatus;
use App\Enums\ImageType;
use App\Models\Image;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This migration:
     * 1. Converts chapters.inline_images JSON from full objects to image ID references
     * 2. Drops legacy image columns from books, chapters, and characters
     */
    public function up(): void
    {
        // First, update chapters.inline_images to reference Image IDs
        $this->migrateInlineImagesToIds();

        // Drop legacy columns from books
        Schema::table('books', function (Blueprint $table) {
            $table->dropColumn([
                'cover_image',
                'cover_image_prompt',
                'cover_image_status',
            ]);
        });

        // Drop legacy columns from chapters
        Schema::table('chapters', function (Blueprint $table) {
            $table->dropColumn([
                'image',
                'image_prompt',
            ]);
        });

        // Drop legacy columns from characters
        Schema::table('characters', function (Blueprint $table) {
            $table->dropColumn([
                'portrait_image',
                'portrait_image_prompt',
            ]);
        });
    }

    /**
     * Convert inline_images JSON from objects to image ID references.
     *
     * Before: [{"url": "...", "prompt": "...", "paragraph_index": 5, "status": "complete"}]
     * After: [{"image_id": "...", "paragraph_index": 5}]
     */
    protected function migrateInlineImagesToIds(): void
    {
        $chapters = DB::table('chapters')
            ->whereNotNull('inline_images')
            ->get();

        foreach ($chapters as $chapter) {
            $inlineImages = json_decode($chapter->inline_images, true);

            if (! is_array($inlineImages) || empty($inlineImages)) {
                continue;
            }

            $updatedImages = [];
            $hasChanges = false;

            foreach ($inlineImages as $inlineImage) {
                // Skip if already migrated (has image_id)
                if (isset($inlineImage['image_id'])) {
                    $updatedImages[] = $inlineImage;

                    continue;
                }

                $hasChanges = true;

                // Find existing Image record or create one
                $existingImage = Image::query()
                    ->where('chapter_id', $chapter->id)
                    ->where('type', ImageType::ChapterInline)
                    ->where('paragraph_index', $inlineImage['paragraph_index'] ?? null)
                    ->first();

                if ($existingImage) {
                    // Use existing Image record
                    $updatedImages[] = [
                        'image_id' => $existingImage->id,
                        'paragraph_index' => $inlineImage['paragraph_index'] ?? 0,
                    ];
                } else {
                    // Create a new Image record
                    $status = match ($inlineImage['status'] ?? null) {
                        'complete' => ImageStatus::Complete,
                        'error' => ImageStatus::Error,
                        'pending' => ImageStatus::Pending,
                        'cancelled' => ImageStatus::Cancelled,
                        default => ($inlineImage['url'] ?? null) ? ImageStatus::Complete : ImageStatus::Pending,
                    };

                    $imageId = (string) Str::ulid();

                    DB::table('images')->insert([
                        'id' => $imageId,
                        'book_id' => $chapter->book_id,
                        'chapter_id' => $chapter->id,
                        'user_id' => $chapter->user_id,
                        'profile_id' => $chapter->profile_id,
                        'type' => ImageType::ChapterInline->value,
                        'image_url' => $inlineImage['url'] ?? null,
                        'prompt' => $inlineImage['prompt'] ?? null,
                        'status' => $status->value,
                        'paragraph_index' => $inlineImage['paragraph_index'] ?? 0,
                        'aspect_ratio' => '16:9',
                        'created_at' => $chapter->created_at,
                        'updated_at' => $chapter->updated_at,
                    ]);

                    $updatedImages[] = [
                        'image_id' => $imageId,
                        'paragraph_index' => $inlineImage['paragraph_index'] ?? 0,
                    ];
                }
            }

            if ($hasChanges && ! empty($updatedImages)) {
                DB::table('chapters')
                    ->where('id', $chapter->id)
                    ->update(['inline_images' => json_encode($updatedImages)]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Re-add columns to books
        Schema::table('books', function (Blueprint $table) {
            $table->text('cover_image')->nullable()->after('title');
            $table->text('cover_image_prompt')->nullable()->after('cover_image');
            $table->string('cover_image_status')->nullable()->after('cover_image_prompt');
        });

        // Re-add columns to chapters
        Schema::table('chapters', function (Blueprint $table) {
            $table->text('image')->nullable()->after('body');
            $table->text('image_prompt')->nullable()->after('image');
        });

        // Re-add columns to characters
        Schema::table('characters', function (Blueprint $table) {
            $table->text('portrait_image')->nullable()->after('backstory');
            $table->text('portrait_image_prompt')->nullable()->after('portrait_image');
        });

        // Restore inline_images format from Image IDs back to full objects
        $this->restoreInlineImagesFromIds();
    }

    /**
     * Restore inline_images JSON from image ID references back to full objects.
     */
    protected function restoreInlineImagesFromIds(): void
    {
        $chapters = DB::table('chapters')
            ->whereNotNull('inline_images')
            ->get();

        foreach ($chapters as $chapter) {
            $inlineImages = json_decode($chapter->inline_images, true);

            if (! is_array($inlineImages) || empty($inlineImages)) {
                continue;
            }

            $restoredImages = [];

            foreach ($inlineImages as $imageRef) {
                if (! isset($imageRef['image_id'])) {
                    // Already in old format
                    $restoredImages[] = $imageRef;

                    continue;
                }

                $image = DB::table('images')->where('id', $imageRef['image_id'])->first();

                if ($image) {
                    $restoredImages[] = [
                        'paragraph_index' => $imageRef['paragraph_index'],
                        'url' => $image->image_url,
                        'prompt' => $image->prompt,
                        'status' => $image->status,
                    ];
                }
            }

            if (! empty($restoredImages)) {
                DB::table('chapters')
                    ->where('id', $chapter->id)
                    ->update(['inline_images' => json_encode($restoredImages)]);
            }
        }
    }
};
