<?php
// tests/Feature/BookCrudTest.php

use App\Models\Book;
use App\Models\Loan;
use App\Models\User;

// FR-1
it('allows admin to create a book with available_copies equal to total_copies', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)->postJson('/api/books', [
        'title'         => 'Laravel untuk Pemula',
        'author'        => 'Budi',
        'category'      => 'Programming',
        'description'   => 'Buku belajar Laravel',
        'total_copies'  => 5,
    ]);

    $response->assertStatus(201);
    $this->assertDatabaseHas('books', [
        'title'            => 'Laravel untuk Pemula',
        'total_copies'     => 5,
        'available_copies' => 5,
    ]);
});

it('rejects book creation by non-admin', function () {
    $member = User::factory()->create(['role' => 'member']);

    $this->actingAs($member)->postJson('/api/books', [
        'title'        => 'Buku Ilegal',
        'author'       => 'X',
        'category'     => 'Y',
        'total_copies' => 1,
    ])->assertStatus(403);
});

it('rejects book creation without authentication', function () {
    $this->postJson('/api/books', [
        'title'        => 'Buku Tanpa Login',
        'author'       => 'X',
        'category'     => 'Y',
        'total_copies' => 1,
    ])->assertStatus(401);
});

// FR-4
it('allows searching books by title or category without authentication', function () {
    Book::factory()->create(['title' => 'Pemrograman Laravel', 'category' => 'Tech']);
    Book::factory()->create(['title' => 'Resep Masakan', 'category' => 'Kuliner']);

    $response = $this->getJson('/api/books');

    $response->assertStatus(200)
        ->assertJsonCount(2, 'data');
});

it('allows anyone to view a single book', function () {
    $book = Book::factory()->create();

    $this->getJson("/api/books/{$book->id}")
        ->assertStatus(200)
        ->assertJsonPath('data.id', $book->id);
});

// FR-2: validasi total_copies tidak boleh kurang dari loan aktif
it('rejects updating total_copies below active loan count (FR-2)', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $member = User::factory()->create(['role' => 'member']);
    $book = Book::factory()->create(['total_copies' => 5, 'available_copies' => 3]);

    Loan::factory()->count(2)->for($member, 'member')->create([
        'book_id' => $book->id,
        'status'  => 'active',
    ]);

    $response = $this->actingAs($admin)->putJson("/api/books/{$book->id}", [
        'total_copies' => 1,
    ]);

    $response->assertStatus(422);
});

it('allows updating total_copies when above active loan count', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $book = Book::factory()->create(['total_copies' => 5, 'available_copies' => 5]);

    $response = $this->actingAs($admin)->putJson("/api/books/{$book->id}", [
        'total_copies' => 10,
    ]);

    $response->assertStatus(200);
    $this->assertDatabaseHas('books', ['id' => $book->id, 'total_copies' => 10]);
});

// destroy
it('rejects deleting a book with active loans', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $member = User::factory()->create(['role' => 'member']);
    $book = Book::factory()->create();

    Loan::factory()->for($member, 'member')->create([
        'book_id' => $book->id,
        'status'  => 'active',
    ]);

    $this->actingAs($admin)->deleteJson("/api/books/{$book->id}")
        ->assertStatus(409);

    $this->assertDatabaseHas('books', ['id' => $book->id]);
});

it('allows deleting a book with no active loans', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $book = Book::factory()->create();

    $this->actingAs($admin)->deleteJson("/api/books/{$book->id}")
        ->assertStatus(204);

    $this->assertDatabaseMissing('books', ['id' => $book->id]);
});