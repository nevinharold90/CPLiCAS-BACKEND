<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage; // Added missing import

class Book extends Model
{
    use HasFactory;

    protected $fillable = [
        'users_id',
        'title',
        'cover_image',
        'isbn',
        'summary',
        'description',
    ];

    protected $appends = ['image_url'];

    /**
     * Automatically appends the public URL for cover_image to JSON responses.
     */
    public function getImageUrlAttribute(): ?string
    {
        return $this->cover_image ? asset(Storage::url($this->cover_image)) : null;
    }

    public function user(): BelongsTo
    {
        // Aligned with 'users_id' from your $fillable array
        return $this->belongsTo(User::class, 'users_id');
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

    public function bookClassification(): HasOne
    {
        return $this->hasOne(BookClassification::class, 'book_id');
    }

    public function readSession(): HasMany
    {
        return $this->hasMany(ReadSession::class);
    }

    public function bookCopy(): HasMany
    {
        return $this->hasMany(BookCopy::class, 'book_id');
    }
}
