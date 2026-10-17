<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\Organizer;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    private function poster(string $seed): string
    {
        return "https://picsum.photos/seed/{$seed}/900/560";
    }

    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'test@example.com'],
            ['name' => 'Test User', 'password' => Hash::make('password')],
        );

        $student = User::updateOrCreate(
            ['email' => 'student@spc.edu.ph'],
            ['name' => 'SPC Student', 'password' => Hash::make('password')],
        );
        $student->role = 'student';
        $student->save();

        $studentAffairs = Organizer::updateOrCreate(
            ['name' => 'SPC Student Affairs'],
            ['description' => 'Official student affairs announcements and campus activities.'],
        );
        $itOrganization = Organizer::updateOrCreate(
            ['name' => 'IT Student Organization'],
            ['description' => 'Technology talks, workshops, and student-led events.'],
        );
        $organizerAccounts = [
            [$studentAffairs, 'organizer@spc.edu.ph', 'SPC Organizer'],
            [$itOrganization, 'it.organizer@spc.edu.ph', 'IT Organizer'],
        ];
        foreach ($organizerAccounts as [$organizer, $email, $name]) {
            $account = User::updateOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => Hash::make('password')],
            );
            $account->role = 'organizer';
            $account->save();
            $organizer->user()->associate($account);
            $organizer->save();
        }
        $athletics = Organizer::updateOrCreate(
            ['name' => 'SPC Athletics Council'],
            ['description' => 'Intramurals, tournaments, and fitness activities for every student.'],
        );
        $arts = Organizer::updateOrCreate(
            ['name' => 'Arts & Culture Guild'],
            ['description' => 'Music, theater, and visual arts showcases by SPC students.'],
        );

        $events = [
            [
                'title' => 'SPC Foundation Week Opening',
                'description' => 'Join the opening program and celebrate SPC campus life.',
                'poster_url' => $this->poster('foundation'),
                'venue' => 'SPC Main Quadrangle',
                'starts_at' => now()->addDays(4)->setTime(9, 0),
                'ends_at' => now()->addDays(4)->setTime(12, 0),
                'category' => 'Campus',
                'rules' => 'Bring your student ID. Follow event staff instructions.',
                'capacity' => 250,
                'baseline_registered_count' => 168,
                'organizer_id' => $studentAffairs->id,
            ],
            [
                'title' => 'Web Development Workshop',
                'description' => 'A beginner-friendly workshop on modern web development.',
                'poster_url' => $this->poster('webdev'),
                'venue' => 'Computer Laboratory 2',
                'starts_at' => now()->addDays(7)->setTime(13, 0),
                'ends_at' => now()->addDays(7)->setTime(16, 0),
                'category' => 'Workshop',
                'rules' => 'Bring a laptop if available. Register with your student email.',
                'capacity' => 50,
                'baseline_registered_count' => 42,
                'organizer_id' => $itOrganization->id,
            ],
            [
                'title' => 'Student Organization Fair',
                'description' => 'Meet student organizations and discover ways to get involved.',
                'poster_url' => $this->poster('orgfair'),
                'venue' => 'Student Center',
                'starts_at' => now()->addDays(10)->setTime(10, 0),
                'ends_at' => now()->addDays(10)->setTime(15, 0),
                'category' => 'Student Life',
                'rules' => 'Keep walkways clear and respect all booths.',
                'capacity' => 150,
                'baseline_registered_count' => 67,
                'organizer_id' => $studentAffairs->id,
            ],
            [
                'title' => 'Intramurals Basketball Finals',
                'description' => 'Cheer on your department in the championship game.',
                'poster_url' => $this->poster('basketball'),
                'venue' => 'SPC Gymnasium',
                'starts_at' => now()->addDays(12)->setTime(16, 0),
                'ends_at' => now()->addDays(12)->setTime(19, 0),
                'category' => 'Sports',
                'rules' => 'Wear your department color. No outside food inside the gym.',
                'capacity' => 300,
                'baseline_registered_count' => 120,
                'organizer_id' => $athletics->id,
            ],
            [
                'title' => 'Acoustic Night',
                'description' => 'An evening of student bands and open-mic performances.',
                'poster_url' => $this->poster('acoustic'),
                'venue' => 'SPC Auditorium',
                'starts_at' => now()->addDays(15)->setTime(18, 0),
                'ends_at' => now()->addDays(15)->setTime(21, 0),
                'category' => 'Arts & Culture',
                'rules' => 'Doors open at 5:30 PM. Keep phones on silent during performances.',
                'capacity' => 120,
                'baseline_registered_count' => 119,
                'organizer_id' => $arts->id,
            ],
            [
                'title' => 'Hackathon: Build for Campus',
                'description' => 'A 6-hour team hackathon to build tools that improve campus life.',
                'poster_url' => $this->poster('hackathon'),
                'venue' => 'Computer Laboratory 1',
                'starts_at' => now()->addDays(20)->setTime(8, 0),
                'ends_at' => now()->addDays(20)->setTime(14, 0),
                'category' => 'Workshop',
                'rules' => 'Teams of 3 to 4. Bring your own laptop and charger.',
                'capacity' => 60,
                'baseline_registered_count' => 20,
                'organizer_id' => $itOrganization->id,
            ],
        ];

        foreach ($events as $eventData) {
            $event = Event::updateOrCreate(['title' => $eventData['title']], $eventData);

            if ($eventData['title'] === 'SPC Foundation Week Opening') {
                $student->rsvps()->updateOrCreate(
                    ['event_id' => $event->id],
                    ['status' => 'going', 'seat_number' => $event->baseline_registered_count + 1],
                );

                // A few more students going, so "who else is going" has names.
                foreach (['Maria Santos', 'Juan Dela Cruz', 'Angel Reyes', 'Paolo Garcia'] as $i => $name) {
                    $guest = User::updateOrCreate(
                        ['email' => 'guest'.($i + 1).'@spc.edu.ph'],
                        ['name' => $name, 'password' => Hash::make('password')],
                    );
                    $guest->rsvps()->updateOrCreate(
                        ['event_id' => $event->id],
                        ['status' => 'going', 'seat_number' => $event->baseline_registered_count + 2 + $i],
                    );
                }
            }
        }

        $pastEvents = [
            [
                'title' => 'Welcome Back Orientation',
                'description' => 'Orientation for returning and new students.',
                'poster_url' => $this->poster('orientation'),
                'venue' => 'SPC Auditorium',
                'starts_at' => now()->subDays(30)->setTime(9, 0),
                'ends_at' => now()->subDays(30)->setTime(12, 0),
                'category' => 'Campus',
                'rules' => 'Bring your student ID.',
                'capacity' => 300,
                'baseline_registered_count' => 280,
                'organizer_id' => $studentAffairs->id,
            ],
            [
                'title' => 'Intro to Python Bootcamp',
                'description' => 'A hands-on introduction to programming with Python.',
                'poster_url' => $this->poster('python'),
                'venue' => 'Computer Laboratory 2',
                'starts_at' => now()->subDays(14)->setTime(13, 0),
                'ends_at' => now()->subDays(14)->setTime(17, 0),
                'category' => 'Workshop',
                'rules' => 'Laptops provided.',
                'capacity' => 40,
                'baseline_registered_count' => 38,
                'organizer_id' => $itOrganization->id,
            ],
        ];

        foreach ($pastEvents as $eventData) {
            $event = Event::updateOrCreate(['title' => $eventData['title']], $eventData);
            $slug = str($eventData['title'])->slug();
            foreach (range(1, 4) as $n) {
                $event->photos()->updateOrCreate(
                    ['url' => $this->poster("{$slug}-{$n}")],
                    ['caption' => "{$eventData['title']} — photo {$n}"],
                );
            }
        }
    }
}
