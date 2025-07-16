<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition()
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'password' => bcrypt('password'), // senha padrão
            'remember_token' => Str::random(10),
        ];
    }

    public function client()
    {
        return $this->afterCreating(function (User $user) {
            $clientRole = Role::where('name', 'Client')->first();

            if ($clientRole) {
                $user->roles()->attach($clientRole->id);
            }
        });
    }
}
