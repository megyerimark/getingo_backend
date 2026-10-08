<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'privacy_accepted_at',
        'privacy_policy_version',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = [
        'is_premium',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_banned' => 'boolean',
            'xp_points' => 'integer',
            'current_streak' => 'integer',
            'longest_streak' => 'integer',
            'last_learning_activity_on' => 'date',
            'privacy_accepted_at' => 'datetime',
            'subscription_current_period_end' => 'datetime',
            'premium_started_at' => 'datetime',
        ];
    }

    public function getIsPremiumAttribute(): bool
    {
        return $this->plan === 'premium'
            && in_array($this->subscription_status, ['active', 'trialing'], true);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    public function lessonProgress(): HasMany
    {
        return $this->hasMany(LessonProgress::class);
    }

    public function quizCompletions(): HasMany
    {
        return $this->hasMany(QuizCompletion::class);
    }

    public function companion(): HasOne
    {
        return $this->hasOne(UserCompanion::class);
    }

    public function subscriptionPayments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class);
    }

    public function projectSubmissions(): HasMany
    {
        return $this->hasMany(ProjectSubmission::class);
    }

    public function exerciseSubmissions(): HasMany
    {
        return $this->hasMany(ExerciseSubmission::class);
    }

    public function userAchievements(): HasMany
    {
        return $this->hasMany(UserAchievement::class);
    }

    public function achievements(): BelongsToMany
    {
        return $this->belongsToMany(Achievement::class, 'user_achievements')
            ->withPivot('unlocked_at')
            ->withTimestamps();
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification());
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
