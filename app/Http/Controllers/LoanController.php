<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\IndexLoanRequest;
use App\Http\Requests\ShowLoanRequest;
use App\Http\Requests\StoreLoanRequest;
use App\Http\Resources\LoanResources;

class LoanController extends Controller
{
    public function index(IndexLoanRequest $request){
        $loans = Loan::paginate(25);
        return LoanResource::collection($loans);
    }

    public function store(StoreLoanRequest $request){
        $validate = $request->validate();
        $loans = Loan::create($validate);
        return new LoanResource($loans);
    }

    public function show(ShowLoanRequest $request){
        $loans = Loan::findOrFail($id);
        return new LoanResource($loans);
    }
}
