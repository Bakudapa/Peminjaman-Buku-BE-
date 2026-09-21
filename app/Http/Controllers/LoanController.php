<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexLoanRequest;
use App\Http\Requests\ReturnLoanRequest;
use App\Http\Requests\ShowLoanRequest;
use App\Http\Requests\StoreLoanRequest;
use App\Http\Resources\LoanResource;
use App\Models\Loan;
use App\Services\LoanService;

class LoanController extends Controller
{
    public function __construct(private LoanService $loans) {}

    public function index(IndexLoanRequest $request)
    {
        $loans = Loan::with(['book', 'member'])->latest()->paginate(25);
        return LoanResource::collection($loans);
    }

    public function store(StoreLoanRequest $request)
    {
        $loan = $this->loans->borrowBook(
            $request->user(),
            $request->integer('book_id')
        );

        return LoanResource::make($loan)->response()->setStatusCode(201);
    }

    public function show(ShowLoanRequest $request, Loan $loan)
    {
        return new LoanResource($loan->load('book'));
    }

    public function returnBook(ReturnLoanRequest $request, Loan $loan)
    {
        return new LoanResource($this->loans->returnBook($loan));
    }
}