<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventReport;
use App\Models\Organizer;
use App\Models\Rsvp;
use App\Models\User;
use App\Notifications\AnnouncementPosted;
use App\Notifications\EventCancelled;
use App\Notifications\EventReminder;
use App\Notifications\EventTimeChanged;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EventLifecycleTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array{0: User, 1: Organizer}
     */
    private function createOrganizer(string $email = 'organizer@example.com'): array
    {
        $user = User::factory()->create(['email' => $email]);
        $user->role = 'organizer';
        $user->save();
        $organizer = $user->organizer()->create([
            'name' => 'Test Organization',
            'description' => 'Organization description',
        ]);

        return [$user, $organizer];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function eventData(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Lifecycle Event',
            'description' => 'An event used to test the event lifecycle.',
            'poster_url' => 'https://example.com/poster.jpg',
            'venue' => 'SPC Hall',
            'starts_at' => now()->addDays(5)->toDateTimeString(),
            'ends_at' => now()->addDays(5)->addHours(2)->toDateTimeString(),
            'category' => 'Workshop',
            'rules' => 'Bring an ID.',
            'capacity' => 50,
        ], $overrides);
    }

    private function createEvent(Organizer $organizer, array $overrides = []): Event
    {
        return $organizer->events()->create($this->eventData($overrides));
    }

    private function createRsvp(User $user, Event $event): Rsvp
    {
        return Rsvp::create([
            'user_id' => $user->id,
            'event_id' => $event->id,
            'status' => Rsvp::STATUS_GOING,
            'seat_number' => 1,
        ]);
    }

    public function test_organizer_can_cancel_an_event_and_every_attendee_is_notified(): void
    {
        Notification::fake();
        [$organizerUser, $organizer] = $this->createOrganizer();
        $event = $this->createEvent($organizer);
        $attendee = User::factory()->create();
        $this->createRsvp($attendee, $event);
        $token = $organizerUser->createToken('feature-test')->plainTextToken;

        $this->withToken($token)
            ->postJson("/api/organizer/events/{$event->id}/cancel", [
                'reason' => 'The venue is no longer available.',
            ])
            ->assertOk()
            ->assertJsonPath('event.status', Event::STATUS_CANCELLED)
            ->assertJsonPath('event.cancellation_reason', 'The venue is no longer available.');

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'status' => Event::STATUS_CANCELLED,
            'cancellation_reason' => 'The venue is no longer available.',
        ]);
        $this->assertNotNull($event->fresh()->cancelled_at);

        Notification::assertSentTo($attendee, EventCancelled::class);

        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        $this->getJson("/api/events/{$event->id}")
            ->assertOk()
            ->assertJsonPath('is_cancelled', true)
            ->assertJsonPath('status', Event::STATUS_CANCELLED)
            ->assertJsonPath('cancellation_reason', 'The venue is no longer available.');
    }

    public function test_cancelled_events_reject_new_rsvps_and_check_ins(): void
    {
        [$organizerUser, $organizer] = $this->createOrganizer();
        $event = $this->createEvent($organizer);
        $student = User::factory()->create();
        $token = $student->createToken('feature-test')->plainTextToken;
        $organizerToken = $organizerUser->createToken('feature-test')->plainTextToken;

        $this->withToken($organizerToken)
            ->postJson("/api/organizer/events/{$event->id}/cancel")
            ->assertOk();

        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        $this->withToken($token)
            ->postJson("/api/events/{$event->id}/rsvp")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'This event has been cancelled.');

        $this->withToken($token)
            ->postJson("/api/events/{$event->id}/check-in", ['code' => $event->check_in_code])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'This event has been cancelled.');
    }

    public function test_organizer_can_reactivate_a_cancelled_event(): void
    {
        [$organizerUser, $organizer] = $this->createOrganizer();
        $event = $this->createEvent($organizer, ['status' => Event::STATUS_CANCELLED]);
        $token = $organizerUser->createToken('feature-test')->plainTextToken;

        $this->withToken($token)
            ->postJson("/api/organizer/events/{$event->id}/restore")
            ->assertOk()
            ->assertJsonPath('event.status', Event::STATUS_PUBLISHED);

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'status' => Event::STATUS_PUBLISHED,
            'cancelled_at' => null,
        ]);
    }

    public function test_event_views_are_counted_on_the_organizer_dashboard(): void
    {
        [$organizerUser, $organizer] = $this->createOrganizer();
        $event = $this->createEvent($organizer);
        $token = $organizerUser->createToken('feature-test')->plainTextToken;

        $this->getJson("/api/events/{$event->id}")->assertOk();
        $this->getJson("/api/events/{$event->id}")->assertOk();
        $this->getJson("/api/events/{$event->id}")->assertOk();

        $this->assertDatabaseHas('events', ['id' => $event->id, 'views_count' => 3]);

        $this->withToken($token)
            ->getJson('/api/organizer/events')
            ->assertOk()
            ->assertJsonPath('data.0.views_count', 3)
            ->assertJsonPath('data.0.active_rsvps_count', 0);

        $this->withToken($token)
            ->getJson('/api/organizer/stats')
            ->assertOk()
            ->assertJsonPath('total_views', 3);
    }

    public function test_students_can_report_a_listing_and_organizers_can_resolve_it(): void
    {
        [$organizerUser, $organizer] = $this->createOrganizer();
        $event = $this->createEvent($organizer);
        $student = User::factory()->create(['name' => 'Report Student']);
        $token = $student->createToken('feature-test')->plainTextToken;

        $report = $this->withToken($token)
            ->postJson("/api/events/{$event->id}/report", [
                'reason' => 'wrong_date',
                'details' => 'The date on the poster does not match the listing.',
            ])
            ->assertCreated()
            ->assertJsonPath('report.reason', 'wrong_date');
        $reportId = $report->json('report.id');

        $this->withToken($token)
            ->postJson("/api/events/{$event->id}/report", [
                'reason' => 'wrong_details',
                'details' => 'Updated report from the same student.',
            ])
            ->assertOk()
            ->assertJsonPath('report.id', $reportId);

        $this->assertDatabaseCount('event_reports', 1);
        $this->assertDatabaseHas('event_reports', [
            'id' => $reportId,
            'event_id' => $event->id,
            'user_id' => $student->id,
            'reason' => 'wrong_details',
            'status' => EventReport::STATUS_OPEN,
        ]);

        $organizerToken = $organizerUser->createToken('feature-test')->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        $this->withToken($organizerToken)
            ->getJson('/api/organizer/reports')
            ->assertOk()
            ->assertJsonPath('data.0.id', $reportId)
            ->assertJsonPath('data.0.user.name', 'Report Student')
            ->assertJsonPath('data.0.event.id', $event->id);

        $this->withToken($organizerToken)
            ->postJson("/api/organizer/reports/{$reportId}/resolve")
            ->assertOk()
            ->assertJsonPath('report.status', EventReport::STATUS_RESOLVED);

        $this->assertNotNull(EventReport::findOrFail($reportId)->resolved_at);
    }

    public function test_organizer_cannot_report_their_own_event(): void
    {
        [$organizerUser, $organizer] = $this->createOrganizer();
        $event = $this->createEvent($organizer);
        $token = $organizerUser->createToken('feature-test')->plainTextToken;

        $this->withToken($token)
            ->postJson("/api/events/{$event->id}/report", ['reason' => 'wrong_date'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'You cannot report your own event.');
    }

    public function test_students_can_update_their_profile_name_course_and_photo(): void
    {
        $student = User::factory()->create([
            'name' => 'Old Name',
            'course' => null,
        ]);
        $token = $student->createToken('feature-test')->plainTextToken;

        $this->withToken($token)
            ->putJson('/api/profile', [
                'name' => 'Updated Name',
                'course' => 'BS Information Technology',
                'photo_url' => 'https://example.com/avatar.jpg',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.course', 'BS Information Technology')
            ->assertJsonPath('data.photo_url', 'https://example.com/avatar.jpg');

        $this->assertDatabaseHas('users', [
            'id' => $student->id,
            'name' => 'Updated Name',
            'course' => 'BS Information Technology',
            'photo_url' => 'https://example.com/avatar.jpg',
        ]);

        $this->withToken($token)
            ->getJson('/api/profile')
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('notification_preferences.event_reminders', true);
    }

    public function test_students_can_upload_a_profile_photo(): void
    {
        Storage::fake('public');
        $student = User::factory()->create();
        $token = $student->createToken('feature-test')->plainTextToken;

        $response = $this->withToken($token)
            ->postJson('/api/profile/photo', [
                'photo' => UploadedFile::fake()->image('avatar.jpg'),
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Profile photo updated.');

        $photoUrl = $response->json('data.photo_url');
        $this->assertNotEmpty($photoUrl);

        $path = ltrim(parse_url($photoUrl, PHP_URL_PATH), '/');
        $path = str_replace('storage/', '', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_notification_preferences_can_be_changed(): void
    {
        $student = User::factory()->create();
        $token = $student->createToken('feature-test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/settings/notifications')
            ->assertOk()
            ->assertJsonPath('data.announcements', true)
            ->assertJsonPath('data.event_updates', true)
            ->assertJsonPath('data.event_reminders', true);

        $this->withToken($token)
            ->putJson('/api/settings/notifications', [
                'announcements' => false,
                'event_reminders' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.announcements', false)
            ->assertJsonPath('data.event_updates', true)
            ->assertJsonPath('data.event_reminders', false);

        $student = $student->fresh();
        $this->assertFalse($student->wantsNotification('announcements'));
        $this->assertTrue($student->wantsNotification('event_updates'));
        $this->assertFalse($student->wantsNotification('event_reminders'));
    }

    public function test_disabled_notification_preferences_stop_reminders_updates_and_announcements(): void
    {
        Notification::fake();
        [$organizerUser, $organizer] = $this->createOrganizer();
        $event = $this->createEvent($organizer, [
            'starts_at' => now()->addHours(5)->toDateTimeString(),
            'ends_at' => now()->addHours(7)->toDateTimeString(),
        ]);
        $student = User::factory()->create(['notification_preferences' => [
            'announcements' => false,
            'event_updates' => false,
            'event_reminders' => false,
        ]]);
        $this->createRsvp($student, $event);
        $student->savedEvents()->attach($event->id);

        $this->artisan('events:send-reminders')->assertSuccessful();
        Notification::assertNotSentTo($student, EventReminder::class);

        $organizerToken = $organizerUser->createToken('feature-test')->plainTextToken;
        $this->withToken($organizerToken)
            ->putJson("/api/organizer/events/{$event->id}", $this->eventData([
                'starts_at' => now()->addHours(8)->toDateTimeString(),
                'ends_at' => now()->addHours(10)->toDateTimeString(),
            ]))
            ->assertOk();
        Notification::assertNotSentTo($student, EventTimeChanged::class);

        $this->withToken($organizerToken)
            ->postJson('/api/organizer/announcements', [
                'title' => 'Preference Test',
                'body' => 'This should not notify the student.',
            ])
            ->assertCreated();
        Notification::assertNotSentTo($student, AnnouncementPosted::class);
    }

    public function test_notification_preferences_are_respected_when_enabled(): void
    {
        Notification::fake();
        [$organizerUser, $organizer] = $this->createOrganizer();
        $event = $this->createEvent($organizer, [
            'starts_at' => now()->addHours(5)->toDateTimeString(),
            'ends_at' => now()->addHours(7)->toDateTimeString(),
        ]);
        $student = User::factory()->create();
        $this->createRsvp($student, $event);

        $this->artisan('events:send-reminders')->assertSuccessful();

        Notification::assertSentTo($student, EventReminder::class);
    }
}
