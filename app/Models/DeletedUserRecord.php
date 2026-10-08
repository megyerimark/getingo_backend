<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeletedUserRecord extends Model
{
    protected $fillable = [
        'original_user_id', 'name', 'email', 'role', 'reason', 'evidence_path',
        'evidence_original_name', 'deleted_by_user_id', 'deleted_at',
    ];

    protected $hidden = ['evidence_path'];

    protected function casts(): array { return ['deleted_at' => 'datetime']; }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by_user_id');
    }
}
