<?php

namespace App\Http\Controllers;

use App\Http\Requests\DestroyBookRequest;
use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Http\Resources\BookResource;
use App\Models\Book;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class BookController extends Controller
{
    public function index(Request $request)
    {
        $category = $request->query('category');
        $keyword  = $request->query('keyword');
        $page     = $request->query('page', 1);

        Cache::add('books:cache:version', 1); // pastikan key benar-benar tersimpan
        $version = Cache::get('books:cache:version');

        if ($category && !$keyword) {
            $key = "books:v{$version}:category:{$category}:page:{$page}";

            $books = Cache::remember($key, 60, function () use ($category) {
                return Book::where('category', $category)->paginate(25);
            });

            return BookResource::collection($books);
        }

        $query = Book::query();
        if ($keyword) {
            $query->where('title', 'ilike', "%{$keyword}%");
        }
        if ($category) {
            $query->where('category', $category);
        }

        return BookResource::collection($query->paginate(25));
    }

    public function store(StoreBookRequest $request)
    {
        $validated = $request->validated();
        $validated['available_copies'] = $validated['total_copies'];

        $book = Book::create($validated);
        Cache::increment('books:cache:version');

        return BookResource::make($book)->response()->setStatusCode(201);
    }

    public function show(Book $book)
    {
        return new BookResource($book);
    }

    public function update(UpdateBookRequest $request, Book $book)
    {
        $book->update($request->validated());
        Cache::increment('books:cache:version');

        return new BookResource($book);
    }

    public function destroy(DestroyBookRequest $request, Book $book)
    {
        if ($book->loans()->where('status', 'active')->exists()) {
            abort(409, 'Buku tidak bisa dihapus karena masih ada peminjaman aktif.');
        }

        $book->delete();
        Cache::increment('books:cache:version');

        return response()->json(null, 204);
    }
}