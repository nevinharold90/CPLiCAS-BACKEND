<?php

namespace App\Http\Controllers\Books;

use App\Http\Controllers\Controller;
use App\Models\Book;
use Illuminate\Http\Request;

class IsbnController extends Controller
{
    public function searchIsbn(Request $request)
    {
        $query = $request->query('q');

        if (!$query) {
            return response()->json([]);
        }

        $books = Book::select('id', 'isbn', 'title', 'cover_image', 'summary')
            ->where('isbn', 'LIKE', "%{$query}%")
            ->orWhere('title', 'LIKE', "%{$query}%")
            ->limit(15)
            ->get();

        return response()->json($books);
    }
}
