<?php
// tests/Feature/Concurrency/LoanConcurrencyTest.php

use App\Models\Book;
use App\Models\Loan;
use App\Models\User;
use App\Services\LoanService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    Artisan::call('migrate:fresh');
});

afterEach(function () {
    Artisan::call('migrate:fresh');
});

it('never oversells stock under concurrent borrow requests (FR-10)', function () {
    $book = Book::factory()->create([
        'total_copies'     => 5,
        'available_copies' => 5,
    ]);

    $members = User::factory()->count(10)->create(['role' => 'member']);
    $bookId  = $book->id;
    $memberIds = $members->pluck('id')->all();

    DB::disconnect();

    $pids = [];

    foreach ($memberIds as $memberId) {
        $pid = pcntl_fork();

        if ($pid === -1) {
            throw new RuntimeException('Fork gagal.');
        }

        if ($pid === 0) {
            DB::reconnect();

            try {
                $member = User::find($memberId);
                app(LoanService::class)->borrowBook($member, $bookId);
            } catch (\Throwable $e) {
                // ditelan sengaja
            }

            exit(0);
        }

        $pids[] = $pid;
    }

    foreach ($pids as $pid) {
        pcntl_waitpid($pid, $status);
    }

    DB::reconnect();

    $book->refresh();

    expect($book->available_copies)->toBeGreaterThanOrEqual(0);
    expect($book->available_copies)->toBe(5 - Loan::where('book_id', $bookId)->count());
});