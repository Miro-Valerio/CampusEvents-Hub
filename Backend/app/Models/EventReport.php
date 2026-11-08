<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventReport extends Model
{
    protected $fillable = ['event_id', 'user_id', 'reason', 'details', 'status', 'reviewed_by', 'review_notes', 'reviewed_at'];

    protected $casts = ['reviewed_at' => 'datetime'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
