<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserCompanion extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'care_points',
        'growth_points',
        'water',
        'hunger',
        'happiness',
        'selected_skin',
        'selected_room',
        'last_interaction_at',
        'last_decay_at',
    ];

    protected function casts(): array
    {
        return [
            'care_points' => 'integer',
            'growth_points' => 'integer',
            'water' => 'integer',
            'hunger' => 'integer',
            'happiness' => 'integer',
            'last_interaction_at' => 'datetime',
            'last_decay_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
