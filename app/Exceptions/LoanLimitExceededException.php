<?php
// app/Exceptions/LoanLimitExceededException.php
namespace App\Exceptions;

use App\Models\User;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class LoanLimitExceededException extends Exception
{
    public function __construct(private User $member, private int $limit)
    {
        parent::__construct('Member has reached active loan limit.');
    }

    public function report(): void
    {
        Log::warning('loan.rejected', [
            'reason'    => 'limit_exceeded',
            'member_id' => $this->member->id,
            'limit'     => $this->limit,
        ]);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => "Batas maksimal {$this->limit} pinjaman aktif telah tercapai.",
            'code'    => 'LOAN_LIMIT_EXCEEDED',
        ], 409);
    }
}