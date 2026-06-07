<?php

namespace Database\Factories;

use App\Enums\TravelOrderStatus;
use App\Models\TravelOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TravelOrder>
 */
class TravelOrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'destination' => fake()->city(),
            'departure_date' => fake()->dateTimeBetween('+1 week', '+2 months')->format('Y-m-d'),
            'return_date' => fake()->dateTimeBetween('+2 months', '+3 months')->format('Y-m-d'),
            'status' => TravelOrderStatus::REQUESTED,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TravelOrderStatus::APPROVED,
        ]);
    }

    public function canceled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TravelOrderStatus::CANCELED,
        ]);
    }
}
