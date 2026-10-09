<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'seen_at',
    ];
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    protected function casts(): array
{
    return [
        'seen_at' => 'datetime',
    ];
}

}
