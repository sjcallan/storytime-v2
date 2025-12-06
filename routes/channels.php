<?php

use App\Models\Book;
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
