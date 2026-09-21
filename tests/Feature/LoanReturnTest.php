<?php
// tests/Feature/LoanReturnTest.php

use App\Models\Book;
use App\Models\Loan;
use App\Models\User;

it('returns a loan and increments stock (FR-11)', function () {
    $member = User::factory()->create(['role' => 'member']);
    $book = Book::factory()->create(['total_copies' => 2, 'available_copies' => 1]);
    $loan = Loan::factory()->for($member, 'member')->create([
        'book_id' => $book->id,
        'status' => 'active',
        'due_at' => now()->addDays(3),
    ]);

    $response = $this->actingAs($member)
        ->postJson("/api/loans/{$loan->id}/return");

    $response->assertStatus(200)
        ->assertJsonPath('data.status', 'returned');

    expect($book->fresh()->available_copies)->toBe(2);
    expect($loan->fresh()->returned_at)->not->toBeNull();
});

it('rejects returning an already-returned loan', function () {
    $member = User::factory()->create(['role' => 'member']);
    $book = Book::factory()->create(['available_copies' => 1]);
    $loan = Loan::factory()->for($member, 'member')->create([
        'book_id' => $book->id,
        'status' => 'returned',
        'returned_at' => now(),
    ]);

    $this->actingAs($member)
        ->postJson("/api/loans/{$loan->id}/return")
        ->assertStatus(409)
        ->assertJson(['code' => 'LOAN_ALREADY_RETURNED']);
});

it('rejects returning someone else\'s loan (FR-7)', function () {
    $owner = User::factory()->create(['role' => 'member']);
    $stranger = User::factory()->create(['role' => 'member']);
    $book = Book::factory()->create(['available_copies' => 1]);
    $loan = Loan::factory()->for($owner, 'member')->create([
        'book_id' => $book->id,
        'status' => 'active',
    ]);

    $this->actingAs($stranger)
        ->postJson("/api/loans/{$loan->id}/return")
        ->assertStatus(403);
});