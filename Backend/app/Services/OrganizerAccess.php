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
            ->whereHas('department', fn (Builder $department) => $department
                ->where('is_active', true)
                ->where('organizer_user_id', $user->id));
    }

    public function resolve(User $user, ?int $organizationId = null): Organizer
    {
        $query = $this->query($user);
        if ($organizationId) {
            $query->whereKey($organizationId);
        }

        return $query->orderBy('organizers.id')->firstOrFail();
    }
}
