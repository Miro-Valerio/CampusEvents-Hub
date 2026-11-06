<?php

namespace Database\Factories;

use App\Models\Announcement;
use App\Models\Organizer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Announcement>
 */
class AnnouncementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organizer_id' => Organizer::factory(),
            'user_id' => User::factory(),
            'title' => fake()->sentence(6),
            'body' => fake()->paragraphs(2, true),
            'course' => null,
            'pinned' => false,
        ];
    }

    public function pinned(): static
    {
        return $this->state(fn (array $attributes) => ['pinned' => true]);
    }

    public function forCourse(string $course): static
    {
        return $this->state(fn (array $attributes) => ['course' => $course]);
    }
}
