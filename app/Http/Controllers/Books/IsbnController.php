<?php

namespace App\Http\Controllers\Books;

use App\Http\Controllers\Controller;
use App\Models\Book;
use Illuminate\Http\Request;

class IsbnController extends Controller
{
// App\Http\Controllers\BookController.php (or wherever searchIsbn lives)

public function searchIsbn(Request $request)
{
    try {
        $query = trim($request->query('q', ''));

        if (strlen($query) < 2) {
            return response()->json([]);
        }
        $books = Book::with([
                'authors:id,full_name',
                // Update relation name here
                'bookClassification:id,book_id,dewey_decimal_id,book_type,cutter,year_published,category,place_of_publication',
                'bookClassification.deweyDecimal:id,dewey_number,class_name'
            ])
            ->select([
                'id',
                'title',
                'cover_image',
                'isbn11',
                'isbn13',
                'issn',
                'summary',
                'description'
            ])
            ->where(function ($q) use ($query) {
                $q->where('title', 'LIKE', "%{$query}%")
                    ->orWhere('isbn13', 'LIKE', "%{$query}%")
                    ->orWhere('isbn11', 'LIKE', "%{$query}%")
                    ->orWhere('issn', 'LIKE', "%{$query}%")
                    ->orWhereHas('authors', function ($authorQuery) use ($query) {
                        $authorQuery->where('full_name', 'LIKE', "%{$query}%");
                    })
                    // Update relation name here
                    ->orWhereHas('bookClassification', function ($classQuery) use ($query) {
                        $classQuery->where('cutter', 'LIKE', "%{$query}%")
                            ->orWhere('category', 'LIKE', "%{$query}%");
                    });
            })
            ->limit(15)
            ->get();

        return response()->json($books);

    } catch (\Throwable $e) {
        // This will send the actual exception message back to React with a 500 status
        return response()->json([
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ], 500);
    }
}
}
