<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    protected $fillable = ['code', 'name', 'description', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_legacy' => 'boolean'];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'department', 'code');
    }

    public function organizers(): HasMany
    {
        return $this->hasMany(Organizer::class);
    }
}
