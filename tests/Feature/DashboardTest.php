<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertStatus(200);
    }

    public function test_dashboard_shows_books_grouped_by_genre()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create books with different genres
        $fantasyBook = \App\Models\Book::factory()->create([
            'user_id' => $user->id,
            'genre' => 'fantasy',
            'title' => 'Fantasy Adventure',
        ]);

        $adventureBook = \App\Models\Book::factory()->create([
            'user_id' => $user->id,
            'genre' => 'adventure',
            'title' => 'Great Adventure',
        ]);

        $response = $this->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->has('booksByGenre')
            ->has('booksByGenre.fantasy', 1)
            ->has('booksByGenre.adventure', 1)
            ->where('userName', $user->name)
        );
    }

    public function test_dashboard_only_shows_current_users_books()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $this->actingAs($user);

        // Create book for current user
        $userBook = \App\Models\Book::factory()->create([
            'user_id' => $user->id,
            'genre' => 'fantasy',
        ]);

        // Create book for other user
        $otherBook = \App\Models\Book::factory()->create([
            'user_id' => $otherUser->id,
            'genre' => 'mystery',
        ]);

        $response = $this->get(route('dashboard'));

        $response->assertInertia(fn ($page) => $page
            ->has('booksByGenre.fantasy', 1)
            ->missing('booksByGenre.mystery')
        );
    }

    public function test_dashboard_excludes_books_without_genre()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        \App\Models\Book::factory()->create([
            'user_id' => $user->id,
            'genre' => null,
        ]);

        \App\Models\Book::factory()->create([
            'user_id' => $user->id,
            'genre' => 'fantasy',
        ]);

        $response = $this->get(route('dashboard'));

        $response->assertInertia(fn ($page) => $page
            ->has('booksByGenre', 1)
            ->has('booksByGenre.fantasy', 1)
        );
    }
}
