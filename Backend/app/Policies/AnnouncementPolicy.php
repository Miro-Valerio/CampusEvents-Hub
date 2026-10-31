<?php

namespace App\Policies;

use App\Models\Announcement;
use App\Models\User;

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
            && $user->organizer()->whereKey($announcement->organizer_id)->exists();
    }
}
