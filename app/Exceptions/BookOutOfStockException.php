<?php
// app/Exceptions/BookOutOfStockException.php
namespace App\Exceptions;

use App\Models\Book;
use App\Models\User;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class BookOutOfStockException extends Exception
{
    public function __construct(private User $member, private Book $book)
    {
        parent::__construct('Book is out of stock.');
    }

    public function report(): void
    {
        Log::warning('loan.rejected', [
            'reason'    => 'out_of_stock',
            'member_id' => $this->member->id,
            'book_id'   => $this->book->id,
        ]);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => 'Buku sedang tidak tersedia.',
            'code'    => 'BOOK_OUT_OF_STOCK',
        ], 409);
    }
}