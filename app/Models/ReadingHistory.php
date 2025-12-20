<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReadingHistory extends Model
{
    use HasUlids;

    protected $table = 'reading_history';

    protected $fillable = [
        'user_id',
        'book_id',
        'profile_id',
        'chapter_id',
        'last_read_at',
        'current_chapter_number',
    ];

    protected function casts(): array
    {
        return [
            'last_read_at' => 'datetime',
            'current_chapter_number' => 'integer',
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

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }
}
