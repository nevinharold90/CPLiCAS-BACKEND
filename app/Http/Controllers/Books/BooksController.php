<?php

namespace App\Http\Controllers\Books;

use App\Http\Controllers\BaseController;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

use App\Models\Book;
use App\Models\BookClassification;
use App\Models\DeweyDecimal;
use App\Models\BookAuthor;
use App\Models\Author;
use App\Models\BookCopy;

class BooksController extends BaseController
{
    public function bookIndex()
    {
        $books = Book::with([
            'authors:id,full_name,background',
            'bookCopy:id,book_id,barcode_data,qrcode_data,location,accession_number_id,condition,status',
            'bookClassification:id,book_id,dewey_decimal_id,book_type,cutter,year_published,category',
            'bookClassification.deweyDecimal:id,dd_number,dd_name'
        ])
        ->get(['id', 'title', 'cover_image', 'isbn']);

        $books->each(function ($book) {
            // Hide pivot metadata on authors
            $book->authors->makeHidden('pivot');

            if ($book->bookClassification) {
                // 1. Assign call_number to root book level
                $book->call_number = $book->bookClassification->call_number;

                // 2. Hide call_number inside the nested bookClassification object to prevent duplicate key
                $book->bookClassification->makeHidden('call_number');
            } else {
                $book->call_number = null;
            }
        });

        return response()->json([
            'success' => true,
            'data'    => $books
        ]);
    }


    // Book Registration Function Starts Here
    public function registerBook(Request $request)
    {
        // 1. Validate incoming data
        $validator = Validator::make($request->all(), [
            'title'                => 'required|string|max:255',
            'isbn13'               => 'nullable|string|max:17|unique:books,isbn13',
            'isbn11'               => 'nullable|string|max:15',
            'issn'                 => 'nullable|string|max:10',
            'cover_image'          => 'nullable|string',
            'summary'              => 'nullable|string',
            'description'          => 'nullable|string',
            'author_ids'           => 'required|array|min:1',
            'author_ids.*'         => 'required|string',
            'book_type'            => 'required|string',

            // Required ONLY for non-fiction, optional/nullable for fiction
            'dewey_decimal_id'     => 'required_if:book_type,non-fiction|nullable|exists:dewey_decimals,id',

            'cutter'               => 'required|string',
            'year_published'       => 'required|digits:4',
            'location'             => 'required|string',
            'category'             => 'required|string',
            'place_of_publication' => 'required|string',

            'material_type'        => 'nullable|string',
            'source_of_fund'       => 'nullable|string',
            'condition'            => 'nullable|string',
            'number_of_copies'     => 'nullable|integer|min:1',
        ], [
            'isbn13.unique' => 'A book with this ISBN-13 already exists in the database.'
        ]);

        // Handle authentication resolution early
        $user = $request->user('sanctum') ?? $request->user();

        if (!$user && !app()->isLocal()) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required. No valid Bearer token found.',
                'error'   => 'Unauthenticated'
            ], 401);
        }

        $userId = $user?->id ?? 1; // Fallback to 1 for local testing

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()
            ], 422);
        }

        try {
            // 2. Perform database transaction
            $response = DB::transaction(function () use ($request, $userId) {

                // A. Save Main Book Record
                $book = Book::create([
                    'users_id'    => $userId,
                    'title'       => $request->title,
                    'isbn13'      => $request->isbn13,
                    'isbn11'      => $request->isbn11,
                    'issn'        => $request->issn,
                    'cover_image' => $request->cover_image,
                    'summary'     => $request->summary,
                    'description' => $request->description,
                ]);

                // B. Dynamic Author Lookup / Auto-creation
                foreach ($request->author_ids as $authorInput) {
                    if (is_numeric($authorInput)) {
                        $authorId = (int) $authorInput;
                    } else {
                        $author = Author::firstOrCreate([
                            'full_name' => trim($authorInput)
                        ]);
                        $authorId = $author->id;
                    }

                    BookAuthor::create([
                        'book_id'   => $book->id,
                        'author_id' => $authorId,
                    ]);
                }

                // C. Save Book Classification
                $dewey_id = $request->book_type === 'fiction' ? null : $request->dewey_decimal_id;

                $classification = BookClassification::create([
                    'book_id'              => $book->id,
                    'dewey_decimal_id'     => $dewey_id,
                    'book_type'            => $request->book_type,
                    'cutter'               => $request->cutter,
                    'year_published'       => $request->year_published,
                    'category'             => $request->category,
                    'place_of_publication' => $request->place_of_publication,
                ]);

                // D. Construct Call Number
                if ($request->book_type === 'fiction') {
                    $prefix = 'F';
                } else {
                    $dewey = DeweyDecimal::find($request->dewey_decimal_id);
                    $prefix = $dewey->dd_number ?? $dewey->class_number ?? '';
                }

                $generatedCallNumber = trim("{$prefix} {$request->cutter} {$request->year_published}");

                // E. Generate Book Copies with Auto-generated Barcodes
                $copiesCount = $request->number_of_copies ?? 1;
                $registeredCopies = [];

                for ($i = 0; $i < $copiesCount; $i++) {
                    $uniqueIdentifier = strtoupper(Str::random(8));
                    $barcodeData     = 'CPL-' . date('Y') . '-' . mt_rand(100000, 999999);
                    $qrCodeData      = 'QR-CPL-' . $book->id . '-' . $uniqueIdentifier;
                    $accessionNumber = 'ACC-' . date('Y') . '-' . sprintf('%06d', mt_rand(1, 999999));

                    $copy = BookCopy::create([
                        'users_id'            => $userId,
                        'book_id'             => $book->id,
                        'barcode_data'        => $barcodeData,
                        'qrcode_data'         => $qrCodeData,
                        'location'            => $request->location,
                        'accession_number_id' => $accessionNumber,
                        'status'              => 'available',
                        'source_of_fund'      => $request->source_of_fund ?? 'Purchased',
                        'condition'           => $request->condition ?? 'Good',
                        'material_type'       => $request->material_type ?? 'Book',
                    ]);

                    $registeredCopies[] = $copy;
                }

                return [
                    'book'           => $book,
                    'classification' => $classification,
                    'call_number'    => $generatedCallNumber,
                    'copies'         => $registeredCopies,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Book and copies registered successfully.',
                'data'    => $response
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to register book.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
    // Book Registration Function Ends Here
}
