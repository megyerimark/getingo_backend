<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LessonCodeNote extends Model
{
    protected $fillable = [
        'user_id',
        'lesson_id',
        'html_code',
        'css_code',
        'javascript_code',
        'code',
        'language'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }
}