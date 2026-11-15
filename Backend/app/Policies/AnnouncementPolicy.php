<?php

namespace App\Policies;

use App\Models\Announcement;
use App\Models\User;
use App\Services\OrganizerAccess;

class AnnouncementPolicy
{
    public function update(User $user, Announcement $announcement): bool
    {
        return $this->owns($user, $announcement);
    }

    public function delete(User $user, Announcement $announcement): bool
    {
        return $this->owns($user, $announcement);
    }

    public function pin(User $user, Announcement $announcement): bool
    {
        return $this->owns($user, $announcement);
    }

    private function owns(User $user, Announcement $announcement): bool
    {
        return $user->role === 'organizer'
            && $announcement->organizer_id !== null
            && app(OrganizerAccess::class)->query($user)->whereKey($announcement->organizer_id)->exists();
    }
}
