<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Moderation extends Model
{
    use HasUlids, SoftDeletes;

    protected $fillable = [
        'user_id',
        'profile_id',
        'input',
        'response',
        'flagged',
        'categories',
        'category_scores',
        'model',
        'moderation_id',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'response' => 'array',
            'flagged' => 'boolean',
            'categories' => 'array',
            'category_scores' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    /**
     * Check if any category is flagged.
     */
    public function hasAnyFlaggedCategory(): bool
    {
        if (! $this->categories) {
            return false;
        }

        return collect($this->categories)->contains(true);
    }

    /**
     * Get the list of flagged categories.
     *
     * @return array<string>
     */
    public function getFlaggedCategories(): array
    {
        if (! $this->categories) {
            return [];
        }

        return collect($this->categories)
            ->filter(fn ($value) => $value === true)
            ->keys()
            ->toArray();
    }
}
