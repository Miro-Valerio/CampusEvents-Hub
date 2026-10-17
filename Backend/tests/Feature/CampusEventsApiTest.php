<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Organizer;
use App\Models\Rsvp;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CampusEventsApiTest extends TestCase
{
    use LazilyRefreshDatabase;

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

    private function eventData(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Organizer Event',
            'description' => 'An event created by an organizer.',
            'poster_url' => 'https://example.com/poster.jpg',
            'venue' => 'SPC Hall',
            'starts_at' => now()->addDays(5)->toDateTimeString(),
            'ends_at' => now()->addDays(5)->addHours(2)->toDateTimeString(),
            'category' => 'Workshop',
            'rules' => 'Bring an ID.',
            'capacity' => 50,
        ], $overrides);
    }

    public function test_events_are_returned_as_paginated_json(): void
    {
        $this->seed();

        $response = $this->getJson('/api/events');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.title', 'SPC Foundation Week Opening')
            ->assertJsonPath('total', 6);
    }

    public function test_registration_returns_a_token_and_persists_the_user(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Campus Student',
            'email' => 'campus.student@example.com',
            'password' => 'secure-password',
            'password_confirmation' => 'secure-password',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('user.email', 'campus.student@example.com')
            ->assertJsonPath('user.role', 'student')
            ->assertJsonMissingPath('user.password')
            ->assertJsonPath('token', fn (string $token): bool => $token !== '');

        $this->assertDatabaseHas('users', [
            'email' => 'campus.student@example.com',
        ]);
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $response->json('user.id'),
            'name' => 'campus-events-web',
        ]);
    }

    public function test_registration_creates_an_organizer_and_rejects_unapproved_roles(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Event Organizer',
            'email' => 'new.organizer@example.com',
            'password' => 'secure-password',
            'password_confirmation' => 'secure-password',
            'role' => 'organizer',
            'organization_name' => 'Campus Arts Club',
            'description' => 'Arts and culture events.',
        ])
            ->assertCreated()
            ->assertJsonPath('user.role', 'organizer')
            ->assertJsonPath('user.organizer.name', 'Campus Arts Club')
            ->assertJsonPath('user.organizer.description', 'Arts and culture events.');

        $this->assertDatabaseHas('organizers', ['name' => 'Campus Arts Club']);

        $this->postJson('/api/register', [
            'name' => 'Admin Attempt',
            'email' => 'admin.attempt@example.com',
            'password' => 'secure-password',
            'password_confirmation' => 'secure-password',
            'role' => 'admin',
        ])->assertUnprocessable()->assertJsonValidationErrors(['role']);

        $this->assertDatabaseMissing('users', ['email' => 'admin.attempt@example.com']);
    }

    public function test_login_and_current_user_include_role_and_organizer_profile(): void
    {
        [$user, $organizer] = $this->createOrganizer();
        $user->forceFill(['password' => bcrypt('secure-password')])->save();

        $login = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'secure-password',
        ])->assertOk()
            ->assertJsonPath('user.role', 'organizer')
            ->assertJsonPath('user.organizer.id', $organizer->id);

        $this->withToken($login->json('token'))
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('role', 'organizer')
            ->assertJsonPath('organizer.id', $organizer->id);
    }

    public function test_student_is_forbidden_from_organizer_routes(): void
    {
        $student = User::factory()->create();
        $token = $student->createToken('feature-test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/organizer/events')
            ->assertForbidden();
        $this->withToken($token)
            ->getJson('/api/organizer/stats')
            ->assertForbidden();
    }

    public function test_organizer_cannot_edit_another_organizers_event(): void
    {
        [$firstUser] = $this->createOrganizer('first.organizer@example.com');
        [, $secondOrganizer] = $this->createOrganizer('second.organizer@example.com');
        $event = $secondOrganizer->events()->create($this->eventData());
        $token = $firstUser->createToken('feature-test')->plainTextToken;

        $this->withToken($token)
            ->putJson("/api/organizer/events/{$event->id}", $this->eventData(['title' => 'Unauthorized change']))
            ->assertForbidden();

        $this->assertDatabaseHas('events', ['id' => $event->id, 'title' => 'Organizer Event']);
    }

    public function test_organizer_can_create_update_and_delete_an_event(): void
    {
        [$user, $organizer] = $this->createOrganizer();
        $token = $user->createToken('feature-test')->plainTextToken;

        $created = $this->withToken($token)
            ->postJson('/api/organizer/events', $this->eventData())
            ->assertCreated()
            ->assertJsonPath('organizer_id', $organizer->id)
            ->assertJsonPath('title', 'Organizer Event');
        $eventId = $created->json('id');
        $this->assertDatabaseHas('events', ['id' => $eventId, 'organizer_id' => $organizer->id]);

        $this->withToken($token)
            ->putJson("/api/organizer/events/{$eventId}", $this->eventData([
                'title' => 'Updated Organizer Event',
                'capacity' => 25,
            ]))
            ->assertOk()
            ->assertJsonPath('title', 'Updated Organizer Event')
            ->assertJsonPath('capacity', 25);

        Rsvp::create([
            'user_id' => User::factory()->create()->id,
            'event_id' => $eventId,
            'status' => Rsvp::STATUS_GOING,
            'seat_number' => 1,
        ]);
        Rsvp::create([
            'user_id' => User::factory()->create()->id,
            'event_id' => $eventId,
            'status' => Rsvp::STATUS_GOING,
            'seat_number' => 2,
        ]);
        $this->withToken($token)
            ->putJson("/api/organizer/events/{$eventId}", $this->eventData(['capacity' => 1]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['capacity']);
        $this->withToken($token)
            ->putJson("/api/organizer/events/{$eventId}", $this->eventData(['capacity' => 2]))
            ->assertOk();

        $this->withToken($token)
            ->deleteJson("/api/organizer/events/{$eventId}")
            ->assertOk()
            ->assertJsonPath('message', 'Event deleted successfully.');
        $this->assertDatabaseMissing('events', ['id' => $eventId]);
    }

    public function test_organizer_can_manage_attendees_photos_profile_and_stats(): void
    {
        [$user, $organizer] = $this->createOrganizer();
        $token = $user->createToken('feature-test')->plainTextToken;
        $event = $organizer->events()->create($this->eventData([
            'starts_at' => now()->subDays(3),
            'ends_at' => now()->subDays(3)->addHours(2),
            'baseline_registered_count' => 4,
        ]));
        $attendee = User::factory()->create(['name' => 'Campus Attendee']);
        Rsvp::create([
            'user_id' => $attendee->id,
            'event_id' => $event->id,
            'status' => Rsvp::STATUS_GOING,
            'seat_number' => 5,
        ]);

        $this->withToken($token)
            ->getJson("/api/organizer/events/{$event->id}/attendees")
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Campus Attendee')
            ->assertJsonPath('data.0.email', $attendee->email)
            ->assertJsonPath('data.0.seat_number', 5)
            ->assertJsonPath('data.0.status', 'going');

        $photo = $this->withToken($token)
            ->postJson("/api/organizer/events/{$event->id}/photos", [
                'url' => 'https://example.com/event-photo.jpg',
                'caption' => 'Event memories',
            ])
            ->assertCreated()
            ->assertJsonPath('caption', 'Event memories');

        $this->withToken($token)
            ->deleteJson("/api/organizer/photos/{$photo->json('id')}")
            ->assertOk();

        $this->withToken($token)
            ->putJson('/api/organizer/profile', [
                'name' => 'Updated Organization',
                'description' => 'Updated organization profile.',
            ])
            ->assertOk()
            ->assertJsonPath('name', 'Updated Organization');

        $this->withToken($token)
            ->getJson('/api/organizer/stats')
            ->assertOk()
            ->assertJsonPath('total_events', 1)
            ->assertJsonPath('upcoming_events', 0)
            ->assertJsonPath('total_rsvps', 1)
            ->assertJsonPath('seats_filled', 5);
    }

    public function test_organizer_cannot_add_photos_to_an_upcoming_event(): void
    {
        [$user, $organizer] = $this->createOrganizer();
        $event = $organizer->events()->create($this->eventData());
        $token = $user->createToken('feature-test')->plainTextToken;

        $this->withToken($token)
            ->postJson("/api/organizer/events/{$event->id}/photos", [
                'url' => 'https://example.com/event-photo.jpg',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['event']);
    }

    public function test_saving_an_event_returns_401_without_a_token(): void
    {
        $this->seed();
        $event = Event::query()->firstOrFail();

        $response = $this->postJson("/api/events/{$event->id}/save");

        $response->assertUnauthorized();
    }

    public function test_authenticated_user_can_save_an_event_with_a_bearer_token(): void
    {
        $this->seed();
        $event = Event::query()->firstOrFail();
        $user = User::factory()->create();
        $token = $user->createToken('feature-test')->plainTextToken;

        $response = $this->withToken($token)->postJson("/api/events/{$event->id}/save");

        $response
            ->assertOk()
            ->assertJsonPath('saved', true)
            ->assertJsonPath('message', 'Event saved successfully.');
        $this->assertDatabaseHas('event_user_saves', [
            'event_id' => $event->id,
            'user_id' => $user->id,
        ]);

    }

    public function test_logout_revokes_the_current_access_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('feature-test');

        $this->withToken($token->plainTextToken)
            ->postJson('/api/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Logged out successfully.');

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $token->accessToken->id,
        ]);
    }

    public function test_event_feed_counts_active_rsvps_instead_of_demo_counters(): void
    {
        $this->seed();

        $response = $this->getJson('/api/events');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.title', 'SPC Foundation Week Opening')
            ->assertJsonPath('data.0.registered_count', 173);
    }

    public function test_authenticated_user_can_rsvp_once_and_cancel(): void
    {
        $this->seed();
        $event = Event::query()->firstOrFail();
        $user = User::factory()->create();
        $token = $user->createToken('feature-test')->plainTextToken;

        $this->withToken($token)
            ->postJson("/api/events/{$event->id}/rsvp")
            ->assertCreated()
            ->assertJsonPath('rsvped', true)
            ->assertJsonPath('registered_count', 174)
            ->assertJsonPath('seat_number', 174);

        $this->withToken($token)
            ->postJson("/api/events/{$event->id}/rsvp")
            ->assertOk()
            ->assertJsonPath('registered_count', 174)
            ->assertJsonPath('seat_number', 174);

        $this->withToken($token)
            ->deleteJson("/api/events/{$event->id}/rsvp")
            ->assertOk()
            ->assertJsonPath('rsvped', false)
            ->assertJsonPath('registered_count', 173);

        $this->assertDatabaseHas('rsvps', [
            'event_id' => $event->id,
            'user_id' => $user->id,
            'status' => 'cancelled',
        ]);

        $this->withToken($token)
            ->getJson('/api/my-rsvps')
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_rsvp_requires_authentication(): void
    {
        $this->seed();
        $event = Event::query()->firstOrFail();

        $this->postJson("/api/events/{$event->id}/rsvp")
            ->assertUnauthorized();
    }

    public function test_rsvp_seat_is_reused_after_cancellation(): void
    {
        $this->seed();
        $event = Event::query()->firstOrFail();
        $user = User::factory()->create();
        $token = $user->createToken('feature-test')->plainTextToken;

        $this->withToken($token)->postJson("/api/events/{$event->id}/rsvp");
        $this->withToken($token)->deleteJson("/api/events/{$event->id}/rsvp");

        $this->withToken($token)
            ->postJson("/api/events/{$event->id}/rsvp")
            ->assertCreated()
            ->assertJsonPath('seat_number', 174);
    }

    public function test_my_rsvps_returns_event_details_and_assigned_seat_number(): void
    {
        $this->seed();
        $user = User::query()->where('email', 'student@spc.edu.ph')->firstOrFail();
        $token = $user->createToken('feature-test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/my-rsvps')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'SPC Foundation Week Opening')
            ->assertJsonPath('data.0.organizer.name', 'SPC Student Affairs')
            ->assertJsonPath('data.0.seat_number', 169)
            ->assertJsonPath('data.0.registered_count', 173);
    }

    public function test_full_event_rejects_additional_rsvps(): void
    {
        $this->seed();
        $event = Event::query()->firstOrFail();
        $event->update(['capacity' => 1, 'baseline_registered_count' => 0]);
        $event->rsvps()->delete();
        $event->rsvps()->create([
            'user_id' => User::factory()->create()->id,
            'status' => 'going',
            'seat_number' => 1,
        ]);
        $user = User::factory()->create();
        $token = $user->createToken('feature-test')->plainTextToken;

        $this->withToken($token)
            ->postJson("/api/events/{$event->id}/rsvp")
            ->assertConflict()
            ->assertJsonPath('message', 'This event is full.');

        $this->assertDatabaseMissing('rsvps', [
            'event_id' => $event->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_organizers_index_and_show(): void
    {
        $this->seed();

        $this->assertDatabaseHas('users', [
            'email' => 'student@spc.edu.ph',
            'role' => 'student',
        ]);
        $this->assertDatabaseHas('organizers', [
            'name' => 'SPC Student Affairs',
            'user_id' => User::where('email', 'organizer@spc.edu.ph')->value('id'),
        ]);
        $this->assertDatabaseHas('organizers', [
            'name' => 'IT Student Organization',
            'user_id' => User::where('email', 'it.organizer@spc.edu.ph')->value('id'),
        ]);

        $this->getJson('/api/organizers')
            ->assertOk()
            ->assertJsonCount(4, 'data');

        $organizer = Organizer::where('name', 'SPC Student Affairs')->firstOrFail();
        $this->getJson("/api/organizers/{$organizer->id}")
            ->assertOk()
            ->assertJsonPath('name', 'SPC Student Affairs')
            ->assertJsonCount(2, 'events')
            ->assertJsonCount(1, 'past_events');
    }

    public function test_past_events_archive_has_photos(): void
    {
        $this->seed();

        $this->getJson('/api/events/past')
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonCount(4, 'data.0.photos');
    }

    public function test_calendar_returns_events_for_month(): void
    {
        $this->seed();
        $event = Event::query()->firstOrFail();

        $this->getJson('/api/events/calendar?month='.$event->starts_at->format('Y-m'))
            ->assertOk()
            ->assertJsonFragment(['title' => 'SPC Foundation Week Opening']);
    }

    public function test_attendees_are_listed_with_masked_names(): void
    {
        $this->seed();
        $event = Event::query()->firstOrFail();

        $this->getJson("/api/events/{$event->id}/attendees")
            ->assertOk()
            ->assertJsonPath('total', 173)
            ->assertSee('Juan C.');
    }

    public function test_user_can_toggle_calendar_and_list_saves(): void
    {
        $this->seed();
        $event = Event::query()->firstOrFail();
        $user = User::factory()->create();
        $token = $user->createToken('feature-test')->plainTextToken;

        $this->withToken($token)->postJson("/api/events/{$event->id}/calendar")
            ->assertOk()->assertJsonPath('in_calendar', true);
        $this->withToken($token)->getJson('/api/my-calendar')
            ->assertOk()->assertJsonPath('data.0.id', $event->id);
        $this->withToken($token)->postJson("/api/events/{$event->id}/calendar")
            ->assertOk()->assertJsonPath('in_calendar', false);

        $this->withToken($token)->postJson("/api/events/{$event->id}/save");
        $this->withToken($token)->getJson('/api/my-saves')
            ->assertOk()->assertJsonPath('data.0.id', $event->id);
    }

    public function test_cannot_rsvp_to_a_past_event(): void
    {
        $this->seed();
        $event = Event::where('title', 'Welcome Back Orientation')->firstOrFail();
        $token = User::factory()->create()->createToken('feature-test')->plainTextToken;

        $this->withToken($token)->postJson("/api/events/{$event->id}/rsvp")
            ->assertStatus(422);
    }
}
