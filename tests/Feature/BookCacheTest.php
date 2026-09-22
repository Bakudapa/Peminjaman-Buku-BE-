<?php

use App\Models\Book;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

it('serves repeated category listing from cache', function () {
    Book::factory()->count(5)->create(['category' => 'History']);

    DB::enableQueryLog();
    $this->getJson('/api/books?category=History');
    $first = count(DB::getQueryLog());

    DB::flushQueryLog();
    $this->getJson('/api/books?category=History');
    $second = count(DB::getQueryLog());

    expect($second)->toBeLessThan($first);
});

it('invalidates book cache after creating a new book', function () {
    Book::factory()->count(3)->create(['category' => 'Fiction']);
    $this->getJson('/api/books?category=Fiction'); // isi cache

    Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
    $this->postJson('/api/books', Book::factory()->raw(['category' => 'Fiction']));

    $response = $this->getJson('/api/books?category=Fiction');
    expect($response->json('data'))->toHaveCount(4); // bukan 3 dari cache lama
});

it('invalidates book cache after a loan changes stock', function () {
    $book = Book::factory()->create([
        'category' => 'Science',
        'total_copies' => 2,
        'available_copies' => 2,
    ]);
    $member = User::factory()->create();

    $this->getJson('/api/books?category=Science'); // isi cache, available_copies = 2

    Sanctum::actingAs($member);
    $this->postJson('/api/loans', ['book_id' => $book->id]);

    $response = $this->getJson('/api/books?category=Science');
    $cached = collect($response->json('data'))->firstWhere('id', $book->id);

    expect($cached['available_copies'])->toBe(1); // bukan 2 dari cache lama
});