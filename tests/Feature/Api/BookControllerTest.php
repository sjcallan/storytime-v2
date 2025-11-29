<?php

namespace Tests\Feature\Api;

use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_book(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/books', [
            'title' => 'My Amazing Story',
            'genre' => 'fantasy',
            'age_level' => 8,
            'author' => 'John Doe',
            'plot' => 'A tale of adventure and magic',
            'status' => 'draft',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'id',
            'title',
            'genre',
            'age_level',
            'author',
            'plot',
            'status',
            'user_id',
            'created_at',
            'updated_at',
        ]);

        $this->assertDatabaseHas('books', [
            'title' => 'My Amazing Story',
            'genre' => 'fantasy',
            'user_id' => $user->id,
        ]);
    }

    public function test_guest_cannot_create_book(): void
    {
        $response = $this->postJson('/api/books', [
            'title' => 'My Story',
            'genre' => 'fantasy',
        ]);

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_list_their_books(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Book::factory()->count(3)->create(['user_id' => $user->id]);
        Book::factory()->count(2)->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user)->getJson('/api/books');

        $response->assertStatus(200);
        $response->assertJsonCount(3);
    }

    public function test_authenticated_user_can_view_their_book(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->getJson("/api/books/{$book->id}");

        $response->assertStatus(200);
        $response->assertJson([
            'id' => $book->id,
            'title' => $book->title,
        ]);
    }

    public function test_authenticated_user_cannot_view_other_users_book(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user)->getJson("/api/books/{$book->id}");

        $response->assertStatus(403);
    }

    public function test_authenticated_user_can_update_their_book(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->putJson("/api/books/{$book->id}", [
            'title' => 'Updated Title',
            'status' => 'in_progress',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'title' => 'Updated Title',
            'status' => 'in_progress',
        ]);

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'title' => 'Updated Title',
        ]);
    }

    public function test_authenticated_user_can_delete_their_book(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->deleteJson("/api/books/{$book->id}");

        $response->assertStatus(204);
        $this->assertSoftDeleted('books', ['id' => $book->id]);
    }

    public function test_book_creation_validates_age_level(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/books', [
            'title' => 'Test Book',
            'age_level' => 25, // Invalid age level
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['age_level']);
    }

    public function test_book_creation_validates_status(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/books', [
            'title' => 'Test Book',
            'status' => 'invalid_status',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['status']);
    }
}
