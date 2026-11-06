<?php

namespace Database\Factories;

use App\Models\Rsvp;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Rsvp>
 */
class RsvpFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'status' => Rsvp::STATUS_GOING,
        ];
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => ['status' => Rsvp::STATUS_CANCELLED]);
    }
}
