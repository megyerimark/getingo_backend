<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Exercise extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'title',
        'description',
        'difficulty',
        'estimated_time',
        'solution',
    ];

    protected $hidden = [
        'solution',
    ];

    protected function casts(): array
    {
        return ['estimated_time' => 'integer'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(ExerciseSubmission::class);
    }
}
