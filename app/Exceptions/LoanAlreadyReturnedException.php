<?php
// app/Exceptions/LoanAlreadyReturnedException.php
namespace App\Exceptions;

use App\Models\Loan;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class LoanAlreadyReturnedException extends Exception
{
    public function __construct(private Loan $loan)
    {
        parent::__construct('Loan has already been returned.');
    }

    public function report(): void
    {
        Log::warning('loan.return_rejected', [
            'reason'    => 'already_returned',
            'loan_id'   => $this->loan->id,
            'member_id' => $this->loan->member_id,
        ]);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => 'Loan ini sudah dikembalikan sebelumnya.',
            'code'    => 'LOAN_ALREADY_RETURNED',
        ], 409);
    }
}
