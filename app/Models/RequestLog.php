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
        'profile_id',
        'book_id',
        'chapter_id',
        'item_type',
        'type',
        'request',
        'response',
        'response_status_code',
        'response_time',
        'open_ai_id',
        'model',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'input_images_count',
        'output_images_count',
        'cost_per_input_image',
        'cost_per_output_image',
        'cost_per_token',
        'total_cost',
    ];

    protected function casts(): array
    {
        return [
            'response_time' => 'decimal:6',
            'cost_per_token' => 'decimal:8',
            'cost_per_input_image' => 'decimal:8',
            'cost_per_output_image' => 'decimal:8',
            'total_cost' => 'decimal:8',
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

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }
}
