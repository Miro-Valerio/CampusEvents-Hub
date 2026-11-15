<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;
use App\Services\OrganizerAccess;

class EventPolicy
{
    public function view(User $user, Event $event): bool
    {
        return $user->role === 'organizer'
            && $user->is_active
            && app(OrganizerAccess::class)->query($user)->whereKey($event->organizer_id)->exists();
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
