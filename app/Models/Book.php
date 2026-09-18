<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

class Book extends Model
{
    use HasFactory;

    protected $fillable = [
        'users_id',
        'title',
        'cover_image',
        'isbn11',
        'isbn13',
        'issn',
        'summary',
        'description',
    ];

    /**
     * Accessor to return full URL if cover_image stores a relative storage path.
     */
    protected function imageUrl(): Attribute
    {
        return Attribute::make(
            get: function (?string $value) {
                if (!$value) {
                    return null;
                }

                // If already a full URL (http/https), return as is
                if (filter_var($value, FILTER_VALIDATE_URL)) {
                    return $value;
                }

                // Convert relative path (e.g. "covers/abc.jpg") to full URL
                return asset(Storage::url($value));
            }
        );
    }

    public function authors(): BelongsToMany
    {
        return $this->belongsToMany(
            Author::class,
            'book_authors', // pivot table name
            'book_id',      // foreign key on pivot table
            'author_id'     // foreign key on author table
        );
    }
public function user()
    {
        return $this->belongsTo(User::class, 'users_id');
    }

    public function bookClassification(): HasOne
    {
        return $this->hasOne(BookClassification::class, 'book_id');
    }

    public function bookCopies(): HasMany
    {
        return $this->hasMany(BookCopy::class, 'book_id');
    }

    public function readSessions(): HasMany
    {
        return $this->hasMany(ReadSession::class, 'book_id');
    }
}
