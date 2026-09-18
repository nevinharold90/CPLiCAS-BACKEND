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
            'user:id,name,email',
            'authors:id,full_name,background',
            'bookCopy:id,book_id,barcode_data,qrcode_data,location,accession_number_id,condition,status',
            'bookClassification:id,book_id,dewey_decimal_id,book_type,cutter,year_published,category',
            'bookClassification.deweyDecimal:id,dewey_number,class_name'
        ])
        ->get(['id', 'users_id', 'title', 'cover_image', 'isbn13', 'isbn11', 'issn', 'summary', 'description']);

        $books->each(function ($book) {
            // Hide pivot metadata on authors
            $book->authors->makeHidden('pivot');

            if ($book->bookClassification) {
                // 1. Assign call_number to root book level
                $book->call_number = $book->bookClassification->call_number;

                // 2. Hide call_number inside nested bookClassification object to prevent duplicate key
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

    // Book Registration Function
    public function registerBook(Request $request)
    {
        // 1. Normalize casing for string comparisons
        $rawBookType = strtolower(trim($request->input('book_type', '')));
        $isFiction = in_array($rawBookType, ['fiction', 'f']);

        // 2. Validate incoming data (removed unique constraints on ISBNs/ISSN to allow adding new copies)
        $validator = Validator::make($request->all(), [
            'title'                => 'required|string|max:255',
            'isbn13'               => 'nullable|string|max:17',
            'isbn11'               => 'nullable|string|max:15',
            'issn'                 => 'nullable|string|max:10',

            // Flexibly accepts image file uploads or string URLs under any common key
            'cover_image'          => 'nullable',
            'cover_url'            => 'nullable',
            'image_url'            => 'nullable',

            'summary'              => 'nullable|string',
            'description'          => 'nullable|string',
            'author_ids'           => 'required|array|min:1',
            'author_ids.*'         => 'required',
            'book_type'            => 'required|string',

            // Dewey Decimal is required for Non-Fiction only
            'dewey_decimal_id'     => $isFiction ? 'nullable' : 'required',

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
            'dewey_decimal_id.required' => 'Dewey Decimal classification is required for non-fiction books.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()
            ], 422);
        }

        // 3. Resolve authentication
        $user = $request->user('sanctum') ?? $request->user();

        if (!$user && !app()->isLocal()) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required. No valid Bearer token found.',
                'error'   => 'Unauthenticated'
            ], 401);
        }

        $userId = $user?->id ?? 1;

        try {
            // 4. Perform database transaction
            $response = DB::transaction(function () use ($request, $userId, $isFiction) {

                // Check if catalog record already exists by ISBN or ISSN
                $book = null;
                if ($request->filled('isbn13')) {
                    $book = Book::where('isbn13', $request->isbn13)->first();
                } elseif ($request->filled('isbn11')) {
                    $book = Book::where('isbn11', $request->isbn11)->first();
                } elseif ($request->filled('issn')) {
                    $book = Book::where('issn', $request->issn)->first();
                }

                $isExistingMasterRecord = (bool) $book;

                // A. If book does NOT exist, create Master Bibliographic Record & Metadata
                if (!$book) {
                    // Resolve Image File / URL
                    $imageUrl = null;
                    $file = $request->file('cover_image')
                        ?? $request->file('cover_url')
                        ?? $request->file('image_url');

                    if ($file) {
                        $imageUrl = $file->store('covers', 'public');
                    } else {
                        $imageUrl = $request->input('cover_image')
                                ?? $request->input('cover_url')
                                ?? $request->input('image_url');
                    }

                    $book = Book::create([
                        'users_id'    => $userId,
                        'title'       => $request->title,
                        'isbn13'      => $request->isbn13,
                        'isbn11'      => $request->isbn11,
                        'issn'        => $request->issn,
                        'cover_image' => $imageUrl,
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

                    // C. Resolve Dewey Decimal Foreign Key
                    $deweyRecord = null;
                    $deweyId = null;

                    if (!$isFiction && $request->filled('dewey_decimal_id')) {
                        $deweyInput = $request->dewey_decimal_id;

                        if (is_numeric($deweyInput)) {
                            $deweyRecord = DeweyDecimal::find($deweyInput);
                        }

                        if (!$deweyRecord) {
                            $deweyRecord = DeweyDecimal::where('dewey_number', $deweyInput)->first();
                        }

                        $deweyId = $deweyRecord?->id;
                    }

                    // D. Save Book Classification
                    $classification = BookClassification::create([
                        'book_id'              => $book->id,
                        'dewey_decimal_id'     => $deweyId,
                        'book_type'            => $request->book_type,
                        'cutter'               => $request->cutter,
                        'year_published'       => $request->year_published,
                        'category'             => $request->category,
                        'place_of_publication' => $request->place_of_publication,
                    ]);
                } else {
                    // Fetch classification details for response payload if reusing existing book
                    $classification = $book->bookClassification;
                }

                // E. Load relationship for API response
                $book->load(['user', 'authors', 'bookClassification']);

                // F. Construct Call Number Prefix
                $deweyNumber = $book->bookClassification?->deweyDecimal?->dewey_number;
                $prefix = $isFiction ? 'F' : ($deweyNumber ?? $request->dewey_decimal_id ?? '');
                $cutter = $book->bookClassification?->cutter ?? $request->cutter;
                $year = $book->bookClassification?->year_published ?? $request->year_published;

                $generatedCallNumber = trim("{$prefix} {$cutter} {$year}");

                // G. Generate Physical Copies (Runs for BOTH new catalog entries and donations)
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
                    'is_existing_catalog_record' => $isExistingMasterRecord,
                    'book'                       => $book,
                    'classification'             => $classification,
                    'call_number'                => $generatedCallNumber,
                    'new_copies_registered'      => $registeredCopies,
                ];
            });

            $message = $response['is_existing_catalog_record']
                ? 'Existing catalog record found. Added new physical copy/copies successfully.'
                : 'New master book entry and copies registered successfully.';

            return response()->json([
                'success' => true,
                'message' => $message,
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
}
