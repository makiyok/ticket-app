<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\TicketType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketType>
 */
class TicketTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'name' => fake()->randomElement(['一般', '学生', '小学生以下', 'ペア']),
            'price' => fake()->numberBetween(0, 10) * 500,
            'capacity' => fake()->numberBetween(10, 100),
            'sales_starts_at' => now()->subWeek(),
            'sales_ends_at' => now()->addWeek(),
        ];
    }

    /**
     * 販売開始前の券種
     */
    public function beforeSale(): static
    {
        return $this->state(fn (array $attributes) => [
            'sales_starts_at' => now()->addDay(),
            'sales_ends_at' => now()->addWeek(),
        ]);
    }

    /**
     * 販売終了後の券種
     */
    public function afterSale(): static
    {
        return $this->state(fn (array $attributes) => [
            'sales_starts_at' => now()->subWeek(),
            'sales_ends_at' => now()->subDay(),
        ]);
    }
}
