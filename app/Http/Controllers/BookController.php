<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Book;
use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Http\Resources\BookResource;

class BookController extends Controller
{
    public function index(){
        $books = Book::paginate(25);
        return BookResource::collection($books);
    }

    public function store(StoreBookRequest $request){
        $validate = $request->validate();
        $books = Book::create($validate);
        return new BookResource($books);
    }

    public function show(string $id){
        $books = Book::findOrFail($id);
        return new BookResource($books);
    }

    public function update(UpdateBookRequest $request, Book $book)
    {
        $validated = $request->validated();
        $book->update($validated);
        return new BookResource($book);
    }

    public function destroy(DestroyBookRequest $request,Book $book)
    {
        $book->delete();
        return response()->json(null, 204);
    }
}
