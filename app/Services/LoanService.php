<?php
// app/Services/LoanService.php
namespace App\Services;

use App\Exceptions\BookOutOfStockException;
use App\Exceptions\LoanAlreadyReturnedException;
use App\Exceptions\LoanLimitExceededException;
use App\Models\Book;
use App\Models\Loan;
use App\Models\User;
use App\Enums\LoanStatus;
use Illuminate\Support\Facades\DB;

class LoanService
{
    private const MAX_ACTIVE_LOANS = 3;
    private const LOAN_DURATION_DAYS = 7;

    /**
     * Use case: anggota meminjam satu buku.
     * FR-8, FR-9, FR-10, §13.
     */
    public function borrowBook(User $member, int $bookId): Loan
    {
        return DB::transaction(function () use ($member, $bookId) {
            $book = $this->lockBook($bookId);

            $this->ensureUserUnderLimit($member);
            $this->ensureBookAvailable($member, $book);

            $this->decreaseStock($book);

            return $this->createLoan($member, $book);
        });
    }

    /**
     * Use case: anggota mengembalikan buku.
     * FR-11.
     */
    public function returnBook(Loan $loan): Loan
    {
        return DB::transaction(function () use ($loan) {
            $loan = $this->lockLoan($loan->id);

            $this->ensureLoanNotYetReturned($loan);

            $this->markReturned($loan);
            $this->increaseStock($loan->book_id);

            return $loan->fresh();
        });
    }

    // ---------------------------------------------------------------
    // Private steps — masing-masing satu tanggung jawab kecil
    // ---------------------------------------------------------------

    private function lockBook(int $bookId): Book
    {
        return Book::lockForUpdate()->findOrFail($bookId);
    }

    private function lockLoan(int $loanId): Loan
    {
        return Loan::lockForUpdate()->findOrFail($loanId);
    }

    private function ensureBookAvailable(User $member, Book $book): void
    {
        if ($book->available_copies < 1) {
            throw new BookOutOfStockException($member, $book);
        }
    }

    private function ensureUserUnderLimit(User $member): void
    {
        $activeCount = $member->loans()->where('status', LoanStatus::Active)->count();

        if ($activeCount >= self::MAX_ACTIVE_LOANS) {
            throw new LoanLimitExceededException($member, self::MAX_ACTIVE_LOANS);
        }
    }

    private function ensureLoanNotYetReturned(Loan $loan): void
    {
        if ($loan->status === LoanStatus::Returned) {
            throw new LoanAlreadyReturnedException($loan);
        }
    }

    private function decreaseStock(Book $book): void
    {
        $book->decrement('available_copies');
    }

    private function increaseStock(int $bookId): void
    {
        $this->lockBook($bookId)->increment('available_copies');
    }

    private function createLoan(User $member, Book $book): Loan
    {
        return $member->loans()->create([
            'book_id'     => $book->id,
            'borrowed_at' => now(),
            'due_at'      => now()->addDays(self::LOAN_DURATION_DAYS),
            'status'      => LoanStatus::Active,
        ]);
    }

    private function markReturned(Loan $loan): void
    {
        $loan->fill([
            'returned_at' => now(),
            'status'      => LoanStatus::Returned,
        ])->save();
    }
}
