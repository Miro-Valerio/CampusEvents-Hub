<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'role', 'course', 'department', 'profile_photo_url', 'notification_preferences', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'notification_preferences' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function savedEvents(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_user_saves')->withTimestamps();
    }

    public function calendarEvents(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_user_calendar')->withTimestamps();
    }

    public function rsvps(): HasMany
    {
        return $this->hasMany(Rsvp::class);
    }

    public function organizer(): HasOne
    {
        return $this->hasOne(Organizer::class);
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organizer::class)
            ->withPivot('membership_role')
            ->withTimestamps();
    }

    public function departmentRecord(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department', 'code');
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(UserInvitation::class);
    }

    public function departmentMembershipRequests(): HasMany
    {
        return $this->hasMany(DepartmentMembershipRequest::class);
    }

    public function reviewedDepartmentMembershipRequests(): HasMany
    {
        return $this->hasMany(DepartmentMembershipRequest::class, 'reviewed_by');
    }

    public function announcements(): HasMany
    {
        return $this->hasMany(Announcement::class);
    }

    public function announcementReads(): HasMany
    {
        return $this->hasMany(AnnouncementRead::class);
    }

    public function announcementReactions(): HasMany
    {
        return $this->hasMany(AnnouncementReaction::class);
    }

    public function eventReports(): HasMany
    {
        return $this->hasMany(EventReport::class);
    }

    public function wantsNotification(string $preference): bool
    {
        return (bool) ($this->notification_preferences[$preference] ?? true);
    }
}
