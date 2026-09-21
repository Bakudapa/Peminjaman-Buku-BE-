<?php

namespace Database\Factories;

use App\Models\Book;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Book>
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
        $totalCopies = fake()->numberBetween(1, 20);
        return [
            'title'=>fake()->sentence(4),
            'author'=>fake()->name(),
            'category' => fake()->randomElement(['Fiction', 'Non-Fiction', 'Science', 'History', 'Biography']),
            'description'=>fake()->paragraph(),
            'total_copies' => $totalCopies,
            'available_copies' => $totalCopies,
        ];
    }
}
