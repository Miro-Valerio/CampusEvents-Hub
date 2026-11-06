<?php

namespace App\Models;

use Database\Factories\RsvpFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Rsvp extends Model
{
    public const STATUS_GOING = 'going';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = ['user_id', 'event_id', 'status', 'seat_number', 'checked_in_at'];

    protected $casts = ['checked_in_at' => 'datetime'];

    protected static function booted(): void
    {
        static::creating(function (self $rsvp): void {
            $rsvp->check_in_code_salt ??= bin2hex(random_bytes(16));
        });
    }

    /** @use HasFactory<RsvpFactory> */
    use HasFactory;

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function checkInCode(): string
    {
        return strtoupper(substr(hash_hmac(
            'sha256',
            $this->getKey().':'.$this->event_id.':'.$this->check_in_code_salt,
            (string) config('app.key'),
        ), 0, 10));
    }
}
