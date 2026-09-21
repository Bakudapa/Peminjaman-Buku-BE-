<?php
// tests/Feature/Concurrency/LoanConcurrencyTest.php

use App\Exceptions\BookOutOfStockException;
use App\Models\Book;
use App\Models\Loan;
use App\Models\User;
use App\Services\LoanService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

beforeEach(fn () => Artisan::call('migrate:fresh'));
afterEach(fn () => Artisan::call('migrate:fresh'));

if (! function_exists('pcntl_fork')) {
    $this->markTestSkipped('pcntl extension required');
}

it('never oversells stock under concurrent borrow requests (FR-10)', function () {
    $copies = 5;
    $book = Book::factory()->create([
        'total_copies'     => $copies,
        'available_copies' => $copies,
    ]);
    $memberIds = User::factory()->count(30)->create(['role' => 'member'])->pluck('id')->all();
    $bookId = $book->id;

    DB::disconnect();

    $startAt = microtime(true) + 1.0;   // barrier: semua child mulai bersamaan
    $pids = [];

    foreach ($memberIds as $memberId) {
        $pid = pcntl_fork();

        if ($pid === -1) {
            throw new RuntimeException('Fork gagal.');
        }

        if ($pid === 0) {
            $code = 0;
            try {
                DB::reconnect();
                $member = User::findOrFail($memberId);

                while (microtime(true) < $startAt) {
                    usleep(200);
                }

                app(LoanService::class)->borrowBook($member, $bookId);
            } catch (BookOutOfStockException $e) {
                $code = 10;             // ditolak dengan benar
            } catch (\Throwable $e) {
                $code = 99;             // crash tak terduga
                file_put_contents(
                    storage_path("logs/fork-{$memberId}.log"),
                    get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine()
                );
            }
            exit($code);
        }

        $pids[] = $pid;
    }

    $codes = [];

    foreach ($pids as $pid) {
        pcntl_waitpid($pid, $status);
        $codes[] = pcntl_wifexited($status)
            ? pcntl_wexitstatus($status)
            : 'signal:' . pcntl_wtermsig($status);
    }


    $outOfStock = count(array_filter($codes, fn ($c) => $c === 10));
    $unexpected = count(array_filter($codes, fn ($c) => $c !== 0 && $c !== 10));

    DB::reconnect();

    expect($unexpected)->toBe(0);                                     // tidak ada child yang gagal diam-diam
    expect(Loan::where('book_id', $bookId)->count())->toBe($copies); // tepat 5 loan
    expect(Book::find($bookId)->available_copies)->toBe(0);          // stok habis, tidak negatif
    expect($outOfStock)->toBe(count($memberIds) - $copies);          // 25 ditolak
});