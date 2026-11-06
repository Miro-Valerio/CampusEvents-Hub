<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Organizer;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AnnouncementApiTest extends TestCase
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

    private function announcementData(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Campus Announcement',
            'body' => 'Important details for the campus community.',
            'course' => null,
            'pinned' => false,
        ], $overrides);
    }

    private function notificationCount(User $user): int
    {
        return DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $user->id)
            ->count();
    }

    private function useToken(string $token): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    private function signOut(): void
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
    }

    public function test_organizers_can_post_announcements_and_students_cannot(): void
    {
        [$organizerUser, $organizer] = $this->createOrganizer();
        $token = $organizerUser->createToken('feature-test')->plainTextToken;

        $this->useToken($token)
            ->postJson('/api/organizer/announcements', $this->announcementData([
                'title' => 'Foundation Week update',
                'pinned' => true,
            ]))
            ->assertCreated()
            ->assertJsonPath('title', 'Foundation Week update')
            ->assertJsonPath('organizer_id', $organizer->id)
            ->assertJsonPath('user_id', $organizerUser->id)
            ->assertJsonPath('pinned', true);

        $this->assertDatabaseHas('announcements', [
            'title' => 'Foundation Week update',
            'organizer_id' => $organizer->id,
            'pinned' => true,
        ]);

        $student = User::factory()->create(['course' => 'BS Information Technology']);
        $studentToken = $student->createToken('feature-test')->plainTextToken;

        $this->useToken($studentToken)
            ->postJson('/api/organizer/announcements', $this->announcementData(['title' => 'Student post']))
            ->assertForbidden();

        $this->signOut();

        $this->postJson('/api/organizer/announcements', $this->announcementData(['title' => 'Guest post']))
            ->assertUnauthorized();

        $this->assertDatabaseMissing('announcements', ['title' => 'Student post']);
        $this->assertDatabaseMissing('announcements', ['title' => 'Guest post']);
    }

    public function test_course_announcements_notify_only_matching_students(): void
    {
        [$organizerUser] = $this->createOrganizer();
        $token = $organizerUser->createToken('feature-test')->plainTextToken;

        $itStudent = User::factory()->create(['course' => 'BS Information Technology']);
        $businessStudent = User::factory()->create(['course' => 'BS Business Administration']);

        $this->useToken($token)
            ->postJson('/api/organizer/announcements', $this->announcementData([
                'title' => 'BSIT capstone orientation',
                'course' => 'BS Information Technology',
            ]))
            ->assertCreated();

        $this->assertSame(1, $this->notificationCount($itStudent));
        $this->assertSame(0, $this->notificationCount($businessStudent));
        $this->assertSame(0, $this->notificationCount($organizerUser));

        $this->useToken($token)
            ->postJson('/api/organizer/announcements', $this->announcementData([
                'title' => 'Campus-wide announcement',
            ]))
            ->assertCreated();

        $this->assertSame(2, $this->notificationCount($itStudent));
        $this->assertSame(1, $this->notificationCount($businessStudent));
        $this->assertSame(0, $this->notificationCount($organizerUser));
    }

    public function test_students_can_read_notifications_and_mark_them_as_read(): void
    {
        [$organizerUser] = $this->createOrganizer();
        $organizerToken = $organizerUser->createToken('feature-test')->plainTextToken;
        $student = User::factory()->create();
        $studentToken = $student->createToken('feature-test')->plainTextToken;

        $this->useToken($organizerToken)
            ->postJson('/api/organizer/announcements', $this->announcementData(['title' => 'New notice']))
            ->assertCreated();

        $listed = $this->useToken($studentToken)
            ->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('data.0.data.title', 'New notice');

        $notificationId = $listed->json('data.0.id');

        $this->useToken($studentToken)
            ->postJson("/api/notifications/{$notificationId}/read")
            ->assertOk()
            ->assertJsonPath('unread_count', 0);

        $this->useToken($studentToken)
            ->getJson('/api/notifications')
            ->assertJsonPath('unread_count', 0);
    }

    public function test_pinned_announcements_are_returned_first_and_only_owners_can_pin(): void
    {
        [$organizerUser] = $this->createOrganizer();
        [$otherUser] = $this->createOrganizer('other.organizer@example.com');
        $token = $organizerUser->createToken('feature-test')->plainTextToken;
        $otherToken = $otherUser->createToken('feature-test')->plainTextToken;

        $pinned = Announcement::factory()->pinned()->create([
            'organizer_id' => $organizerUser->organizer->id,
            'user_id' => $organizerUser->id,
            'title' => 'Pinned announcement',
            'created_at' => now()->subHour(),
        ]);
        $newer = Announcement::factory()->create([
            'organizer_id' => $organizerUser->organizer->id,
            'user_id' => $organizerUser->id,
            'title' => 'Newer announcement',
        ]);

        $this->getJson('/api/announcements')
            ->assertOk()
            ->assertJsonPath('data.0.id', $pinned->id)
            ->assertJsonPath('data.1.id', $newer->id);

        $this->useToken($token)
            ->postJson("/api/organizer/announcements/{$pinned->id}/pin")
            ->assertOk()
            ->assertJsonPath('pinned', false);

        $this->getJson('/api/announcements')
            ->assertOk()
            ->assertJsonPath('data.0.id', $newer->id);

        $this->useToken($otherToken)
            ->postJson("/api/organizer/announcements/{$pinned->id}/pin")
            ->assertForbidden();

        $this->assertDatabaseHas('announcements', ['id' => $pinned->id, 'pinned' => false]);
    }

    public function test_students_get_unread_highlights_and_can_mark_announcements_read(): void
    {
        [$organizerUser] = $this->createOrganizer();
        $student = User::factory()->create(['course' => 'BS Information Technology']);
        $token = $student->createToken('feature-test')->plainTextToken;

        $general = Announcement::factory()->create([
            'organizer_id' => $organizerUser->organizer->id,
            'user_id' => $organizerUser->id,
            'title' => 'General notice',
        ]);
        Announcement::factory()->forCourse('BS Business Administration')->create([
            'organizer_id' => $organizerUser->organizer->id,
            'user_id' => $organizerUser->id,
            'title' => 'Business only notice',
        ]);

        $this->getJson('/api/announcements')
            ->assertOk()
            ->assertJsonPath('unread_count', 0);

        $studentFeed = $this->useToken($token)
            ->getJson('/api/announcements')
            ->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->json('data');

        $generalFromFeed = collect($studentFeed)->firstWhere('id', $general->id);
        $this->assertFalse($generalFromFeed['is_read']);

        $this->useToken($token)
            ->postJson("/api/announcements/{$general->id}/read")
            ->assertOk()
            ->assertJsonPath('is_read', true)
            ->assertJsonPath('unread_count', 0);

        $readFeed = $this->useToken($token)
            ->getJson('/api/announcements')
            ->assertOk()
            ->json('data');

        $generalFromFeed = collect($readFeed)->firstWhere('id', $general->id);
        $this->assertTrue($generalFromFeed['is_read']);

        $this->useToken($token)
            ->getJson('/api/announcements/unread')
            ->assertOk()
            ->assertJsonPath('unread_count', 0);
    }

    public function test_students_can_toggle_a_single_emoji_reaction(): void
    {
        [$organizerUser] = $this->createOrganizer();
        $student = User::factory()->create();
        $token = $student->createToken('feature-test')->plainTextToken;
        $announcement = Announcement::factory()->create([
            'organizer_id' => $organizerUser->organizer->id,
            'user_id' => $organizerUser->id,
        ]);

        $this->useToken($token)
            ->postJson("/api/announcements/{$announcement->id}/react", ['emoji' => '👍'])
            ->assertOk()
            ->assertJsonPath('reacted', true)
            ->assertJsonPath('reaction_count', 1)
            ->assertJsonPath('reactions.0.emoji', '👍')
            ->assertJsonPath('reactions.0.count', 1)
            ->assertJsonPath('reactions.0.reacted', true);

        $this->useToken($token)
            ->postJson("/api/announcements/{$announcement->id}/react", ['emoji' => '👍'])
            ->assertOk()
            ->assertJsonPath('reacted', false)
            ->assertJsonPath('reaction_count', 0);

        $this->useToken($token)
            ->postJson("/api/announcements/{$announcement->id}/react", ['emoji' => '🎉'])
            ->assertOk();
        $this->useToken($token)
            ->postJson("/api/announcements/{$announcement->id}/react", ['emoji' => '❤️'])
            ->assertOk()
            ->assertJsonPath('reaction_count', 1)
            ->assertJsonPath('reactions.0.emoji', '❤️');

        $this->useToken($token)
            ->postJson("/api/announcements/{$announcement->id}/react", ['emoji' => '🚀'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['emoji']);

        $this->assertDatabaseCount('announcement_reactions', 1);
    }

    public function test_guests_cannot_react_or_mark_announcements_read(): void
    {
        [$organizerUser] = $this->createOrganizer();
        $announcement = Announcement::factory()->create([
            'organizer_id' => $organizerUser->organizer->id,
            'user_id' => $organizerUser->id,
        ]);

        $this->postJson("/api/announcements/{$announcement->id}/react", ['emoji' => '👍'])
            ->assertUnauthorized();
        $this->postJson("/api/announcements/{$announcement->id}/read")
            ->assertUnauthorized();
    }

    public function test_announcements_can_be_filtered_by_course_organization_and_search(): void
    {
        [$firstUser, $firstOrganizer] = $this->createOrganizer('first.organizer@example.com');
        [$secondUser, $secondOrganizer] = $this->createOrganizer('second.organizer@example.com');

        $general = Announcement::factory()->create([
            'organizer_id' => $firstOrganizer->id,
            'user_id' => $firstUser->id,
            'title' => 'General campus notice',
        ]);
        $itOnly = Announcement::factory()->forCourse('BS Information Technology')->create([
            'organizer_id' => $firstOrganizer->id,
            'user_id' => $firstUser->id,
            'title' => 'BSIT lab schedule',
        ]);
        $businessOnly = Announcement::factory()->forCourse('BS Business Administration')->create([
            'organizer_id' => $secondOrganizer->id,
            'user_id' => $secondUser->id,
            'title' => 'Business org assembly',
            'body' => 'Accounting students meet at the auditorium.',
        ]);

        $courseFeed = $this->getJson('/api/announcements?course=BS%20Information%20Technology')
            ->assertOk()
            ->json('data');
        $this->assertEqualsCanonicalizing(
            [$general->id, $itOnly->id],
            array_column($courseFeed, 'id'),
        );

        $this->getJson("/api/announcements?organizer_id={$secondOrganizer->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $businessOnly->id);

        $this->getJson('/api/announcements?search=auditorium')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $businessOnly->id);
    }

    public function test_organizers_can_update_and_delete_their_announcements(): void
    {
        [$organizerUser] = $this->createOrganizer();
        [$otherUser] = $this->createOrganizer('other.organizer@example.com');
        $token = $organizerUser->createToken('feature-test')->plainTextToken;
        $otherToken = $otherUser->createToken('feature-test')->plainTextToken;
        $announcement = Announcement::factory()->create([
            'organizer_id' => $organizerUser->organizer->id,
            'user_id' => $organizerUser->id,
        ]);

        $this->useToken($token)
            ->putJson("/api/organizer/announcements/{$announcement->id}", $this->announcementData([
                'title' => 'Updated announcement',
            ]))
            ->assertOk()
            ->assertJsonPath('title', 'Updated announcement');

        $this->useToken($otherToken)
            ->putJson("/api/organizer/announcements/{$announcement->id}", $this->announcementData([
                'title' => 'Hijacked announcement',
            ]))
            ->assertForbidden();

        $this->useToken($otherToken)
            ->deleteJson("/api/organizer/announcements/{$announcement->id}")
            ->assertForbidden();

        $this->useToken($token)
            ->deleteJson("/api/organizer/announcements/{$announcement->id}")
            ->assertOk();

        $this->assertDatabaseMissing('announcements', ['id' => $announcement->id]);
    }
}
