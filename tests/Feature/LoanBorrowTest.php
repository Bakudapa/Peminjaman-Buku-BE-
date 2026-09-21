<?php
// tests/Feature/LoanBorrowTest.php

use App\Models\Book;
use App\Models\User;

it('rejects borrowing when book is out of stock (FR-9)', function () {
    $member = User::factory()->create(['role' => 'member']);
    $book = Book::factory()->create([
        'total_copies' => 1,
        'available_copies' => 0,
    ]);

    $response = $this->actingAs($member)
        ->postJson('/api/loans', ['book_id' => $book->id]);

    $response->assertStatus(409)
        ->assertJson(['code' => 'BOOK_OUT_OF_STOCK']);

    expect($book->fresh()->available_copies)->toBe(0);
    $this->assertDatabaseCount('loans', 0);
});

it('borrows successfully and decrements stock', function () {
    $member = User::factory()->create(['role' => 'member']);
    $book = Book::factory()->create([
        'total_copies' => 3,
        'available_copies' => 3,
    ]);

    $response = $this->actingAs($member)
        ->postJson('/api/loans', ['book_id' => $book->id]);

    $response->assertStatus(201);
    expect($book->fresh()->available_copies)->toBe(2);
    $this->assertDatabaseHas('loans', [
        'book_id' => $book->id,
        'status' => 'active',
    ]);
});

it('rejects borrowing beyond active loan limit (§13)', function () {
    $member = User::factory()->create(['role' => 'member']);
    $books = Book::factory()->count(4)->create(['available_copies' => 5]);

    foreach ($books->take(3) as $book) {
        $this->actingAs($member)->postJson('/api/loans', ['book_id' => $book->id])
            ->assertStatus(201);
    }

    $response = $this->actingAs($member)
        ->postJson('/api/loans', ['book_id' => $books->last()->id]);

    $response->assertStatus(409)
        ->assertJson(['code' => 'LOAN_LIMIT_EXCEEDED']);
});