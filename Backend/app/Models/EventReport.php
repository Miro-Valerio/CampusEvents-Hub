<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventReport extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_RESOLVED = 'resolved';

    public const REASONS = [
        'wrong_details',
        'wrong_date',
        'spam_or_scam',
        'inappropriate',
        'duplicate',
        'other',
    ];

    protected $fillable = ['event_id', 'user_id', 'reason', 'details', 'status', 'resolved_at'];

    protected $casts = ['resolved_at' => 'datetime'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
