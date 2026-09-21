<?php
// tests/Feature/LoanRateLimitTest.php

use App\Models\Book;
use App\Models\User;

it('throttles loan requests per member after the configured limit', function () {
    $member = User::factory()->create(['role' => 'member']);
    $book = Book::factory()->create(['available_copies' => 100]);

    // limiter 'loans' di-set 10/menit di AppServiceProvider
    for ($i = 1; $i <= 10; $i++) {
        $response = $this->actingAs($member)
            ->postJson('/api/loans', ['book_id' => $book->id]);

        // boleh 201 (berhasil) atau 409 (kena limit 3 loan aktif) —
        // yang penting BUKAN 429, karena belum melewati batas rate limit
        expect($response->status())->not->toBe(429);
    }

    // request ke-11 harus kena limiter
    $response = $this->actingAs($member)
        ->postJson('/api/loans', ['book_id' => $book->id]);

    $response->assertStatus(429);
});

it('does not share rate limit between different members', function () {
    $memberA = User::factory()->create(['role' => 'member']);
    $memberB = User::factory()->create(['role' => 'member']);
    $book = Book::factory()->create(['available_copies' => 100]);

    // habiskan limit member A
    for ($i = 1; $i <= 10; $i++) {
        $this->actingAs($memberA)->postJson('/api/loans', ['book_id' => $book->id]);
    }
    $this->actingAs($memberA)
        ->postJson('/api/loans', ['book_id' => $book->id])
        ->assertStatus(429);

    // member B belum pernah request, harus tidak kena limit
    $response = $this->actingAs($memberB)
        ->postJson('/api/loans', ['book_id' => $book->id]);

    expect($response->status())->not->toBe(429);
});