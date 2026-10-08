<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExerciseSubmission extends Model
{
    protected $fillable = [
        'user_id', 'exercise_id', 'answer', 'started_at', 'expires_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'expires_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function exercise(): BelongsTo { return $this->belongsTo(Exercise::class); }
}
