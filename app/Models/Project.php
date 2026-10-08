<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'difficulty',
        'estimated_time',
        'solution',
        'starter_html',
        'starter_css',
        'starter_javascript',
        'validation_type',
        'expected_output',
        'xp_reward',
    ];

    protected $hidden = [
        'solution',
        'expected_output',
    ];

    protected function casts(): array
    {
        return [
            'estimated_time' => 'integer',
            'xp_reward' => 'integer',
        ];
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(ProjectSubmission::class);
    }
}
