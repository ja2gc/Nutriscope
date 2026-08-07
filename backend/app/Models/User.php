<?php

namespace App\Models;

use App\Models\Concerns\HasDisplayName;
use App\Models\Concerns\HasPublicId;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\Traits\CausesActivity;

class User extends Authenticatable
{
    use CausesActivity, HasApiTokens, HasFactory, Notifiable, SoftDeletes;
    use HasDisplayName, HasPublicId;

    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'email',
        'recovery_email',
        'recovery_email_verified_at',
        'recovery_email_verification_code',
        'recovery_email_verification_expires_at',
        'contact_number',
        'profile_photo',
        'profile_photo_stored_object_id',
        'password',
        'role',
        'is_active',
        'must_change_password',
        'must_set_recovery_email',
        'onboarding_skipped_at',
        'pending_recovery_email',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'recovery_email_verified_at' => 'datetime',
        'recovery_email_verification_expires_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',
        'must_change_password' => 'boolean',
        'must_set_recovery_email' => 'boolean',
        'onboarding_skipped_at' => 'datetime',
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

    public function profilePhotoObject(): BelongsTo
    {
        return $this->belongsTo(StoredObject::class, 'profile_photo_stored_object_id');
    }

    public function isRnd(): bool
    {
        return $this->role === 'RND';
    }

    public function isFss(): bool
    {
        return $this->role === 'FSS';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'Admin';
    }

    public function requiresOnboarding(): bool
    {
        return $this->must_change_password || $this->must_set_recovery_email;
    }

    public function completeOnboardingRequirement(string $attribute): void
    {
        $this->forceFill([$attribute => false]);
        if (! $this->must_change_password && ! $this->must_set_recovery_email) {
            $this->onboarding_skipped_at = null;
        }
        $this->save();
    }
}
