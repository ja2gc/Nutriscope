<?php

namespace App\Models;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\Traits\CausesActivity;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes, CausesActivity;

    protected $fillable = [
        'name',
        'email',
        'recovery_email',
        'recovery_email_verified_at',
        'recovery_email_verification_code',
        'recovery_email_verification_expires_at',
        'contact_number',
        'profile_photo',
        'password',
        'role',
        'is_active',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'recovery_email_verified_at' => 'datetime',
        'recovery_email_verification_expires_at' => 'datetime',
        'password'          => 'hashed',
        'is_active'         => 'boolean',
    ];

    public function getEmailForPasswordReset(): string
    {
        return $this->recovery_email_verified_at && $this->recovery_email
            ? $this->recovery_email
            : $this->email;
    }

    public function routeNotificationForMail(mixed $notification = null): ?string
    {
        if ($notification instanceof ResetPassword) {
            return $this->getEmailForPasswordReset();
        }

        return $this->email;
    }

    public function ncpRecords()
    {
        return $this->hasMany(NcpRecord::class, 'rnd_user_id');
    }

    public function announcements()
    {
        return $this->hasMany(Announcement::class);
    }

    public function recipes()
    {
        return $this->hasMany(Recipe::class, 'rnd_user_id');
    }

    public function calendarEvents()
    {
        return $this->hasMany(CalendarEvent::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function reports()
    {
        return $this->hasMany(Report::class);
    }

    public function isRnd(): bool   { return $this->role === 'RND'; }
    public function isFss(): bool   { return $this->role === 'FSS'; }
    public function isAdmin(): bool { return $this->role === 'Admin'; }
}
