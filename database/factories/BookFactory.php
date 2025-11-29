<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Book>
 */
class BookFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $genres = ['fantasy', 'adventure', 'mystery', 'science_fiction', 'fairy_tale', 'historical'];
        $statuses = ['draft', 'in_progress', 'completed', 'published'];
        $title = fake()->words(rand(2, 4), true);
        
        return [
            'title' => ucwords($title),
            'cover_image' => 'https://api.dicebear.com/7.x/shapes/svg?seed='.urlencode($title).'&backgroundColor=random',
            'status' => fake()->randomElement($statuses),
            'age_level' => fake()->randomElement([4, 5, 6, 7, 8, 9, 10, 11, 12]),
            'genre' => fake()->randomElement($genres),
            'plot' => fake()->paragraphs(2, true),
            'type' => fake()->randomElement(['illustrated', 'chapter_book', 'picture_book']),
            'author' => fake()->name(),
            'last_opened_date' => fake()->dateTimeBetween('-30 days', 'now'),
            'is_published' => fake()->boolean(30),
            'published_at' => fake()->boolean(30) ? fake()->dateTimeBetween('-90 days', 'now') : null,
        ];
    }
}
