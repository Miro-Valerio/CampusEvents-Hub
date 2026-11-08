<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    public function view(User $user, Event $event): bool
    {
        return in_array($user->role, ['organizer', 'faculty'], true)
            && $user->is_active
            && $event->organizer()->where('is_active', true)->whereKey($event->organizer_id)
                ->where(function ($query) use ($user): void {
                    $query->where('user_id', $user->id)
                        ->orWhereHas('members', fn ($members) => $members->whereKey($user->id));
                })
                ->exists();
    }

    public function update(User $user, Event $event): bool
    {
        return $this->view($user, $event);
    }

    public function delete(User $user, Event $event): bool
    {
        return $this->view($user, $event);
    }
}
