<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookClassification extends Model
{
    protected $fillable = [
        'book_id',
        'dewey_decimal_id',
        'book_type',
        'cutter',
        'category',
        'year_published',
        'place_of_publication'
    ];

    /**
     * Append dynamic attributes to JSON responses.
     */
    protected $appends = [
        'call_number',
    ];

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class, 'book_id');
    }

    public function deweyDecimal(): BelongsTo
    {
        return $this->belongsTo(DeweyDecimal::class, 'dewey_decimal_id');
    }

    /**
     * Compute the standardized Call Number.
     */
    protected function callNumber(): Attribute
    {
        return Attribute::make(
            get: function () {
                $isFiction = strtolower(trim($this->book_type ?? '')) === 'fiction';

                // Fixed: Matched ERD column 'dewey_number'
                $prefix = $isFiction
                    ? 'F'
                    : ($this->deweyDecimal?->dewey_number ?? '');

                return trim("{$prefix} {$this->cutter} {$this->year_published}");
            }
        );
    }
}
