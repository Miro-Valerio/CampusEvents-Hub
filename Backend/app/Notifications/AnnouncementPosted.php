<?php

namespace App\Notifications;

use App\Models\Announcement;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class AnnouncementPosted extends Notification
{
    use Queueable;

    public function __construct(public Announcement $announcement) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'announcement_id' => $this->announcement->id,
            'title' => $this->announcement->title,
            'message' => Str::limit($this->announcement->body, 140),
            'organizer' => $this->announcement->organizer?->name,
            'course' => $this->announcement->course,
            'pinned' => $this->announcement->pinned,
        ];
    }
}
