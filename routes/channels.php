<?php

use App\Models\Book;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('characters', function (User $user) {
    return true;
});

Broadcast::channel('book.{bookId}', function (User $user, string $bookId) {
    $book = Book::find($bookId);

    return $book && $book->user_id === $user->id;
});

Broadcast::channel('user.{userId}.books', function (User $user, string $userId) {
    return (string) $user->id === $userId;
});

Broadcast::channel('conversation.{conversationId}', function (User $user, string $conversationId) {
    $conversation = Conversation::find($conversationId);

    return $conversation && $conversation->user_id === $user->id;
});
