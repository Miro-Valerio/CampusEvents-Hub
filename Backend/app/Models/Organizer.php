<?php

namespace App\Models;

use Database\Factories\OrganizerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organizer extends Model
{
    /** @use HasFactory<OrganizerFactory> */
    use HasFactory;

    protected $hidden = ['user_id'];

    protected $fillable = ['name', 'description', 'logo_url'];

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function announcements(): HasMany
    {
        return $this->hasMany(Announcement::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
