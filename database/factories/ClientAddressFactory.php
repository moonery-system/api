<?php

namespace Database\Factories;

use App\Models\ClientAddress;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClientAddressFactory extends Factory
{
    protected $model = ClientAddress::class;

    public function definition()
    {
        return [
            'user_id' => User::factory(),
            'address_line' => $this->faker->streetAddress(),
            'neighborhood' => $this->faker->citySuffix(),
            'city' => $this->faker->city(),
            'state' => 'MG',
            'zip_code' => $this->faker->postcode(),
            'complement' => null,
        ];
    }
}
