<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BugReport extends Model
{
        protected $fillable = [
        'user_id',
        'title',
        'description',
        'type',
        'priority',
        'page_url',
        'browser',
        'platform',
        'screenshot',
        'status',
    ];
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

}
