<?php

namespace App\Http\Controllers;

use App\Http\Requests\DestroyBookRequest;
use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Http\Resources\BookResource;
use App\Models\Book;

class BookController extends Controller
{
    public function index()
    {
        $books = Book::paginate(25);
        return BookResource::collection($books);
    }

    public function store(StoreBookRequest $request)
    {
        $validated = $request->validated();
        $validated['available_copies'] = $validated['total_copies'];

        $book = Book::create($validated);
        return BookResource::make($book)->response()->setStatusCode(201);
    }

    public function show(Book $book)
    {
        return new BookResource($book);
    }

    public function update(UpdateBookRequest $request, Book $book)
    {
        $book->update($request->validated());
        return new BookResource($book);
    }

    public function destroy(DestroyBookRequest $request, Book $book)
    {
        if ($book->loans()->where('status', 'active')->exists()) {
            abort(409, 'Buku tidak bisa dihapus karena masih ada peminjaman aktif.');
        }

        $book->delete();
        return response()->json(null, 204);
    }
}