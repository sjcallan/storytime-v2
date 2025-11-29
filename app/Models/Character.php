<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Character extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'name',
        'gender',
        'description',
        'type',
        'age',
        'book_id',
        'user_id',
        'nationality',
        'backstory',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function chapters(): BelongsToMany
    {
        return $this->belongsToMany(Chapter::class, 'chapter_character')
            ->withPivot(['inner_thoughts', 'experience', 'goals', 'personal_motivations'])
            ->withTimestamps();
    }

    public function chapterCharacters(): HasMany
    {
        return $this->hasMany(ChapterCharacter::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function conversationMessages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class);
    }
}
