<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'course', 'photo_url', 'notification_preferences'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Default notification channels. All start enabled.
     *
     * @var array<string, bool>
     */
    public const DEFAULT_NOTIFICATION_PREFERENCES = [
        'announcements' => true,
        'event_updates' => true,
        'event_reminders' => true,
    ];

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
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function notificationPreferences(): array
    {
        return array_merge(
            self::DEFAULT_NOTIFICATION_PREFERENCES,
            array_intersect_key(
                $this->notification_preferences ?? [],
                self::DEFAULT_NOTIFICATION_PREFERENCES,
            ),
        );
    }

    public function wantsNotification(string $key): bool
    {
        return (bool) ($this->notificationPreferences()[$key] ?? true);
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
}
