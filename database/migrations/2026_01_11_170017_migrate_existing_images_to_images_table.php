<?php

use App\Enums\ImageStatus;
use App\Enums\ImageType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Migrate book covers
        $this->migrateBookCovers();

        // Migrate character portraits
        $this->migrateCharacterPortraits();

        // Migrate chapter header images
        $this->migrateChapterHeaders();

        // Migrate chapter inline images
        $this->migrateChapterInlineImages();
    }

    /**
     * Migrate book cover images.
     */
    protected function migrateBookCovers(): void
    {
        $books = DB::table('books')
            ->whereNotNull('cover_image')
            ->orWhereNotNull('cover_image_prompt')
            ->get();

        foreach ($books as $book) {
            $status = match ($book->cover_image_status ?? null) {
                'complete' => ImageStatus::Complete->value,
                'error' => ImageStatus::Error->value,
                'pending' => ImageStatus::Pending->value,
                default => $book->cover_image ? ImageStatus::Complete->value : ImageStatus::Pending->value,
            };

            $imageId = (string) Str::ulid();

            DB::table('images')->insert([
                'id' => $imageId,
                'book_id' => $book->id,
                'user_id' => $book->user_id,
                'profile_id' => $book->profile_id,
                'type' => ImageType::BookCover->value,
                'image_url' => $book->cover_image,
                'prompt' => $book->cover_image_prompt,
                'status' => $status,
                'aspect_ratio' => '3:4',
                'created_at' => $book->created_at,
                'updated_at' => $book->updated_at,
            ]);

            // Update book with new cover_image_id
            DB::table('books')
                ->where('id', $book->id)
                ->update(['cover_image_id' => $imageId]);
        }
    }

    /**
     * Migrate character portrait images.
     */
    protected function migrateCharacterPortraits(): void
    {
        $characters = DB::table('characters')
            ->whereNotNull('portrait_image')
            ->orWhereNotNull('portrait_image_prompt')
            ->get();

        foreach ($characters as $character) {
            $book = DB::table('books')->where('id', $character->book_id)->first();

            $imageId = (string) Str::ulid();

            DB::table('images')->insert([
                'id' => $imageId,
                'book_id' => $character->book_id,
                'character_id' => $character->id,
                'user_id' => $character->user_id,
                'profile_id' => $book?->profile_id,
                'type' => ImageType::CharacterPortrait->value,
                'image_url' => $character->portrait_image,
                'prompt' => $character->portrait_image_prompt,
                'status' => $character->portrait_image ? ImageStatus::Complete->value : ImageStatus::Pending->value,
                'aspect_ratio' => '1:1',
                'created_at' => $character->created_at,
                'updated_at' => $character->updated_at,
            ]);

            // Update character with new portrait_image_id
            DB::table('characters')
                ->where('id', $character->id)
                ->update(['portrait_image_id' => $imageId]);
        }
    }

    /**
     * Migrate chapter header images.
     */
    protected function migrateChapterHeaders(): void
    {
        $chapters = DB::table('chapters')
            ->whereNotNull('image')
            ->orWhereNotNull('image_prompt')
            ->get();

        foreach ($chapters as $chapter) {
            $imageId = (string) Str::ulid();

            DB::table('images')->insert([
                'id' => $imageId,
                'book_id' => $chapter->book_id,
                'chapter_id' => $chapter->id,
                'user_id' => $chapter->user_id,
                'profile_id' => $chapter->profile_id,
                'type' => ImageType::ChapterHeader->value,
                'image_url' => $chapter->image,
                'prompt' => $chapter->image_prompt,
                'status' => $chapter->image ? ImageStatus::Complete->value : ImageStatus::Pending->value,
                'aspect_ratio' => '16:9',
                'created_at' => $chapter->created_at,
                'updated_at' => $chapter->updated_at,
            ]);

            // Update chapter with new header_image_id
            DB::table('chapters')
                ->where('id', $chapter->id)
                ->update(['header_image_id' => $imageId]);
        }
    }

    /**
     * Migrate chapter inline images from JSON array to individual records.
     */
    protected function migrateChapterInlineImages(): void
    {
        $chapters = DB::table('chapters')
            ->whereNotNull('inline_images')
            ->get();

        foreach ($chapters as $chapter) {
            $inlineImages = json_decode($chapter->inline_images, true);

            if (! is_array($inlineImages)) {
                continue;
            }

            foreach ($inlineImages as $inlineImage) {
                $status = match ($inlineImage['status'] ?? null) {
                    'complete' => ImageStatus::Complete->value,
                    'error' => ImageStatus::Error->value,
                    'pending' => ImageStatus::Pending->value,
                    'cancelled' => ImageStatus::Cancelled->value,
                    default => ($inlineImage['url'] ?? null) ? ImageStatus::Complete->value : ImageStatus::Pending->value,
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
                    'status' => $status,
                    'paragraph_index' => $inlineImage['paragraph_index'] ?? null,
                    'aspect_ratio' => '16:9',
                    'created_at' => $chapter->created_at,
                    'updated_at' => $chapter->updated_at,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Clear the foreign key references first
        DB::table('books')->update(['cover_image_id' => null]);
        DB::table('chapters')->update(['header_image_id' => null]);
        DB::table('characters')->update(['portrait_image_id' => null]);

        // Delete all migrated images
        DB::table('images')->delete();
    }
};
