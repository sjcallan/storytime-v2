<?php

namespace App\Enums;

enum ImageStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Complete = 'complete';
    case Error = 'error';
    case Cancelled = 'cancelled';

    /**
     * Get a human-readable label for this status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Processing => 'Processing',
            self::Complete => 'Complete',
            self::Error => 'Error',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Check if the image is in a final state (not processing).
     */
    public function isFinal(): bool
    {
        return match ($this) {
            self::Pending, self::Processing => false,
            self::Complete, self::Error, self::Cancelled => true,
        };
    }
}
