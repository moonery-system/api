<?php

namespace Database\Factories;

use App\Models\Delivery;
use App\Models\DeliveryItems;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryItems>
 */
class DeliveryItemsFactory extends Factory
{
    protected $model = DeliveryItems::class;

    public function definition()
    {
        return [
            'delivery_id' => Delivery::factory(),
            'name' => $this->faker->words(3, true),
            'description' => null,
            'quantity' => $this->faker->numberBetween(1, 5),
            'weight' => $this->faker->randomFloat(2, 0.1, 20),
        ];
    }
}
