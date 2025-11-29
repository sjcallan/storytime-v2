<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RequestLog extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'user_id',
        'book_id',
        'chapter_id',
        'item_type',
        'request',
        'response',
        'response_status_code',
        'response_time',
        'open_ai_id',
        'model',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'cost_per_token',
        'total_cost',
    ];

    protected function casts(): array
    {
        return [
            'response_time' => 'decimal:6',
            'cost_per_token' => 'decimal:8',
            'total_cost' => 'decimal:8',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }
}
