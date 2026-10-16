<?php

namespace Database\Factories;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = fake()->dateTimeBetween('+1 week', '+3 months');

        return [
            'organizer_id' => User::factory()->organizer(),
            'title' => fake()->realText(30),
            'description' => fake()->realText(200),
            'venue' => fake()->city(),
            'starts_at' => $startsAt,
            'ends_at' => (clone $startsAt)->modify('+2 hours'),
            'status' => EventStatus::Draft,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => ['status' => EventStatus::Draft]);
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => ['status' => EventStatus::Published]);
    }

    public function unpublished(): static
    {
        return $this->state(fn (array $attributes) => ['status' => EventStatus::Unpublished]);
    }

    /**
     * 開催済みのイベント
     */
    public function past(): static
    {
        $startsAt = fake()->dateTimeBetween('-3 months', '-1 week');

        return $this->state(fn (array $attributes) => [
            'starts_at' => $startsAt,
            'ends_at' => (clone $startsAt)->modify('+2 hours'),
        ]);
    }
}
