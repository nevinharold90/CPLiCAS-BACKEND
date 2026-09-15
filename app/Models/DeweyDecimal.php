<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeweyDecimal extends Model
{
    protected $fillable = [
        'dewey_number',
        'class_name',
        'description'
    ];

    public function bookClassification()
    {
        return $this->hasMany(BookClassification::class);
    }
}
