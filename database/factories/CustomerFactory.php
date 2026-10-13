<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'business_name' => fake()->optional()->company(),
            'phone' => '9' . fake()->unique()->numerify('#########'),
            'email' => fake()->optional()->safeEmail(),
            'city' => fake()->city(),
        ];
    }
}
