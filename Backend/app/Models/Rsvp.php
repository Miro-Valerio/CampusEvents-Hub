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

    protected $fillable = ['user_id', 'event_id', 'status', 'seat_number', 'reminder_sent_at', 'checked_in_at'];

    protected $casts = ['reminder_sent_at' => 'datetime', 'checked_in_at' => 'datetime'];

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
}
