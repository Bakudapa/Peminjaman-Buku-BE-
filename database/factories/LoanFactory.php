<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\User;
use App\Enums\LoanStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

class LoanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'book_id'     => Book::factory(),
            'member_id'   => User::factory(),
            'borrowed_at' => now(),
            'due_at'      => now()->addDays(7),
            'returned_at' => null,
            'status'      => LoanStatus::Active,
        ];
    }
}