<?php

namespace App\Enums;

enum ImageType: string
{
    case BookCover = 'book_cover';
    case CharacterPortrait = 'character_portrait';
    case ChapterHeader = 'chapter_header';
    case ChapterInline = 'chapter_inline';

    /**
     * Get the aspect ratio for this image type.
     */
    public function aspectRatio(): string
    {
        return match ($this) {
            self::BookCover => '3:4',
            self::CharacterPortrait => '1:1',
            self::ChapterHeader => '16:9',
            self::ChapterInline => '16:9',
        };
    }

    /**
     * Get a human-readable label for this image type.
     */
    public function label(): string
    {
        return match ($this) {
            self::BookCover => 'Book Cover',
            self::CharacterPortrait => 'Character Portrait',
            self::ChapterHeader => 'Chapter Header',
            self::ChapterInline => 'Chapter Inline',
        };
    }
}
