<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Event extends Model
{
    protected $appends = ['registered_count'];

    protected $fillable = [
        'title', 'description', 'poster_url', 'venue', 'starts_at', 'ends_at',
        'category', 'rules', 'capacity', 'baseline_registered_count', 'organizer_id',
        'check_in_code',
    ];

    protected $hidden = ['check_in_code'];

    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime'];

    protected static function booted(): void
    {
        static::creating(function (Event $event): void {
            if (blank($event->check_in_code)) {
                $event->check_in_code = self::generateCheckInCode();
            }
        });
    }

    public static function generateCheckInCode(): string
    {
        do {
            $code = Str::upper(Str::random(8));
        } while (self::query()->where('check_in_code', $code)->exists());

        return $code;
    }

    protected function registeredCount(): Attribute
    {
        return Attribute::get(fn (mixed $value, array $attributes): int => (int) ($attributes['baseline_registered_count'] ?? 0)
            + (int) ($attributes['active_rsvps_count'] ?? 0));
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(Organizer::class);
    }

    public function savedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_user_saves')->withTimestamps();
    }

    public function photos(): HasMany
    {
        return $this->hasMany(EventPhoto::class);
    }

    public function rsvps(): HasMany
    {
        return $this->hasMany(Rsvp::class);
    }
}
