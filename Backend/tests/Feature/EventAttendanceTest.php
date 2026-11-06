<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Organizer;
use App\Models\Rsvp;
use App\Models\User;
use App\Notifications\EventReminder;
use App\Notifications\EventTimeChanged;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EventAttendanceTest extends TestCase
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
            'title' => 'Attendance Event',
            'description' => 'An event used to test attendance.',
            'poster_url' => 'https://example.com/poster.jpg',
            'venue' => 'SPC Hall',
            'starts_at' => now()->addDay()->toDateTimeString(),
            'ends_at' => now()->addDay()->addHours(2)->toDateTimeString(),
            'category' => 'Workshop',
            'rules' => 'Bring an ID.',
            'capacity' => 50,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createEvent(Organizer $organizer, array $overrides = []): Event
    {
        return $organizer->events()->create($this->eventData($overrides));
    }

    private function createRsvp(User $user, Event $event, array $overrides = []): Rsvp
    {
        return Rsvp::create(array_merge([
            'user_id' => $user->id,
            'event_id' => $event->id,
            'status' => Rsvp::STATUS_GOING,
            'seat_number' => 1,
        ], $overrides));
    }

    public function test_reminders_are_sent_once_to_registered_students_before_the_event(): void
    {
        Notification::fake();
        [, $organizer] = $this->createOrganizer();
        $event = $this->createEvent($organizer, [
            'starts_at' => now()->addHours(5)->toDateTimeString(),
            'ends_at' => now()->addHours(7)->toDateTimeString(),
        ]);
        $student = User::factory()->create();
        $rsvp = $this->createRsvp($student, $event);

        $this->artisan('events:send-reminders')->assertSuccessful();

        Notification::assertSentTo($student, EventReminder::class);
        $this->assertNotNull($rsvp->fresh()->reminder_sent_at);

        $this->artisan('events:send-reminders')->assertSuccessful();

        Notification::assertSentToTimes($student, EventReminder::class, 1);
    }

    public function test_reminders_skip_events_outside_the_reminder_window(): void
    {
        Notification::fake();
        [, $organizer] = $this->createOrganizer();
        $event = $this->createEvent($organizer, [
            'starts_at' => now()->addDays(5)->toDateTimeString(),
            'ends_at' => now()->addDays(5)->addHours(2)->toDateTimeString(),
        ]);
        $student = User::factory()->create();
        $rsvp = $this->createRsvp($student, $event);

        $this->artisan('events:send-reminders')->assertSuccessful();

        Notification::assertNotSentTo($student, EventReminder::class);
        $this->assertNull($rsvp->fresh()->reminder_sent_at);
    }

    public function test_saved_users_are_notified_when_an_event_time_changes(): void
    {
        Notification::fake();
        [$organizerUser, $organizer] = $this->createOrganizer();
        $startsAt = now()->addDays(5)->startOfSecond();
        $event = $this->createEvent($organizer, [
            'starts_at' => $startsAt->format('Y-m-d H:i:s'),
            'ends_at' => $startsAt->copy()->addHours(2)->format('Y-m-d H:i:s'),
        ]);
        $savedStudent = User::factory()->create();
        $savedStudent->savedEvents()->attach($event->id);
        $otherStudent = User::factory()->create();
        $token = $organizerUser->createToken('feature-test')->plainTextToken;

        $this->withToken($token)
            ->putJson("/api/organizer/events/{$event->id}", $this->eventData([
                'starts_at' => $startsAt->copy()->addDay()->format('Y-m-d H:i:s'),
                'ends_at' => $startsAt->copy()->addDay()->addHours(2)->format('Y-m-d H:i:s'),
            ]))
            ->assertOk();

        Notification::assertSentTo($savedStudent, EventTimeChanged::class);
        Notification::assertNotSentTo($otherStudent, EventTimeChanged::class);
    }

    public function test_saved_users_are_not_notified_when_the_event_time_is_unchanged(): void
    {
        Notification::fake();
        [$organizerUser, $organizer] = $this->createOrganizer();
        $startsAt = now()->addDays(5)->startOfSecond();
        $event = $this->createEvent($organizer, [
            'starts_at' => $startsAt->format('Y-m-d H:i:s'),
            'ends_at' => $startsAt->copy()->addHours(2)->format('Y-m-d H:i:s'),
        ]);
        $savedStudent = User::factory()->create();
        $savedStudent->savedEvents()->attach($event->id);
        $token = $organizerUser->createToken('feature-test')->plainTextToken;

        $this->withToken($token)
            ->putJson("/api/organizer/events/{$event->id}", $this->eventData([
                'starts_at' => $startsAt->format('Y-m-d H:i:s'),
                'ends_at' => $startsAt->copy()->addHours(2)->format('Y-m-d H:i:s'),
            ]))
            ->assertOk();

        Notification::assertNotSentTo($savedStudent, EventTimeChanged::class);
    }

    public function test_students_can_check_in_with_the_officer_code(): void
    {
        [, $organizer] = $this->createOrganizer();
        $event = $this->createEvent($organizer);
        $student = User::factory()->create();
        $rsvp = $this->createRsvp($student, $event);
        $token = $student->createToken('feature-test')->plainTextToken;

        $this->withToken($token)
            ->getJson("/api/events/{$event->id}/check-in")
            ->assertOk()
            ->assertJsonPath('rsvped', true)
            ->assertJsonPath('checked_in_at', null)
            ->assertJsonPath('check_in_open', true);

        $this->withToken($token)
            ->postJson("/api/events/{$event->id}/check-in", ['code' => 'WRONG123'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);

        $this->assertNull($rsvp->fresh()->checked_in_at);

        $this->withToken($token)
            ->postJson("/api/events/{$event->id}/check-in", ['code' => strtolower($event->check_in_code)])
            ->assertOk()
            ->assertJsonPath('checked_in', true)
            ->assertJsonPath('already_checked_in', false);

        $this->assertNotNull($rsvp->fresh()->checked_in_at);

        $this->withToken($token)
            ->postJson("/api/events/{$event->id}/check-in", ['code' => $event->check_in_code])
            ->assertOk()
            ->assertJsonPath('already_checked_in', true);
    }

    public function test_check_in_requires_an_rsvp_and_an_active_event(): void
    {
        [, $organizer] = $this->createOrganizer();
        $event = $this->createEvent($organizer);
        $student = User::factory()->create();
        $token = $student->createToken('feature-test')->plainTextToken;

        $this->withToken($token)
            ->postJson("/api/events/{$event->id}/check-in", ['code' => $event->check_in_code])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'You need an RSVP to check in for this event.');

        $endedEvent = $this->createEvent($organizer, [
            'starts_at' => now()->subDays(2)->toDateTimeString(),
            'ends_at' => now()->subDays(2)->addHours(2)->toDateTimeString(),
        ]);
        $this->createRsvp($student, $endedEvent);

        $this->withToken($token)
            ->postJson("/api/events/{$endedEvent->id}/check-in", ['code' => $endedEvent->check_in_code])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'This event has already ended.');

        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        $this->postJson("/api/events/{$event->id}/check-in", ['code' => $event->check_in_code])
            ->assertUnauthorized();
    }

    public function test_check_in_history_lists_only_past_checked_in_events(): void
    {
        [, $organizer] = $this->createOrganizer();
        $pastEvent = $this->createEvent($organizer, [
            'title' => 'Past Check-in Event',
            'starts_at' => now()->subDays(3)->toDateTimeString(),
            'ends_at' => now()->subDays(3)->addHours(2)->toDateTimeString(),
        ]);
        $upcomingEvent = $this->createEvent($organizer, [
            'title' => 'Upcoming Check-in Event',
            'starts_at' => now()->addDays(2)->toDateTimeString(),
            'ends_at' => now()->addDays(2)->addHours(2)->toDateTimeString(),
        ]);
        $student = User::factory()->create();
        $this->createRsvp($student, $pastEvent, ['checked_in_at' => now()->subDays(3)]);
        $this->createRsvp($student, $upcomingEvent, ['checked_in_at' => now()]);
        $token = $student->createToken('feature-test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/my-check-ins')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $pastEvent->id)
            ->assertJsonPath('data.0.title', 'Past Check-in Event')
            ->assertJsonPath('data.0.checked_in_at', fn ($value): bool => ! is_null($value));
    }

    public function test_check_in_history_is_kept_after_an_rsvp_is_cancelled(): void
    {
        [, $organizer] = $this->createOrganizer();
        $event = $this->createEvent($organizer, [
            'starts_at' => now()->subDays(4)->toDateTimeString(),
            'ends_at' => now()->subDays(4)->addHours(2)->toDateTimeString(),
        ]);
        $student = User::factory()->create();
        $this->createRsvp($student, $event, [
            'status' => Rsvp::STATUS_CANCELLED,
            'checked_in_at' => now()->subDays(4),
        ]);
        $token = $student->createToken('feature-test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/my-check-ins')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $event->id);
    }

    public function test_check_in_codes_are_hidden_from_students_but_visible_to_the_owner(): void
    {
        [$organizerUser, $organizer] = $this->createOrganizer();
        $event = $this->createEvent($organizer);
        $token = $organizerUser->createToken('feature-test')->plainTextToken;

        $this->getJson("/api/events/{$event->id}")
            ->assertOk()
            ->assertJsonMissingPath('check_in_code');

        $this->withToken($token)
            ->getJson('/api/organizer/events')
            ->assertOk()
            ->assertJsonPath('data.0.check_in_code', $event->check_in_code);

        $this->withToken($token)
            ->getJson('/api/organizer/stats')
            ->assertOk()
            ->assertJsonPath('total_check_ins', 0);
    }
}
