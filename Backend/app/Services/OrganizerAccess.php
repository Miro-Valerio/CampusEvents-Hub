<?php

namespace App\Services;

use App\Models\Organizer;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class OrganizerAccess
{
    public function query(User $user): Builder
    {
        return Organizer::query()
            ->where('is_active', true)
            ->where(function (Builder $query) use ($user): void {
                $query->where('user_id', $user->id)
                    ->orWhereHas('members', fn (Builder $members) => $members->whereKey($user->id));
            });
    }

    public function resolve(User $user, ?int $organizationId = null): Organizer
    {
        $query = $this->query($user);
        if ($organizationId) {
            $query->whereKey($organizationId);
        } elseif ($user->organizer) {
            $query->whereKey($user->organizer->id);
        } else {
            $query->orderBy('organizers.id');
        }

        return $query->firstOrFail();
    }
}
