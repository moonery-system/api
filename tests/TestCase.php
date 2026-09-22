<?php

namespace Tests;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\DeliveryStatusSeeder;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RelatesPermissionsToRoles;
use Database\Seeders\RolesSeeder;
use App\Services\RabbitMQPublisher;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\FakeRabbitMQPublisher;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Roles, permissions and delivery statuses -- what the domain cannot work without.
     * DatabaseSeeder is deliberately not used here: it also creates 1000 fake clients.
     */
    /**
     * Swaps the AMQP publisher for a fake. Call it in setUp of anything that moves a
     * delivery: the domain publishes notifications, and the broker is not under test.
     */
    protected function fakeBroker(): FakeRabbitMQPublisher
    {
        $fake = new FakeRabbitMQPublisher();

        $this->app->instance(RabbitMQPublisher::class, $fake);

        return $fake;
    }

    protected function seedDomain(): void
    {
        $this->seed([
            PermissionsSeeder::class,
            RolesSeeder::class,
            RelatesPermissionsToRoles::class,
            DeliveryStatusSeeder::class,
        ]);
    }

    protected function userWithRole(string $role): User
    {
        $user = User::factory()->create(['activated_at' => now()]);

        $user->roles()->sync([Role::where('name', $role)->firstOrFail()->id]);

        // hasPermission() caches on the instance, so hand back a clean one.
        return $user->fresh();
    }

    protected function admin(): User
    {
        return $this->userWithRole('Admin');
    }

    protected function client(): User
    {
        return $this->userWithRole('Client');
    }

    protected function deliveryman(): User
    {
        return $this->userWithRole('Delivery Man');
    }

    /**
     * Authenticates on the api guard directly instead of minting a JWT.
     *
     * Passing a real token does not survive several requests in one test: jwt-auth
     * keeps the first parsed token on its singleton, so the second request silently
     * stays as the first user and every assertion after it lies. actingAs is the
     * mechanism Laravel provides for switching identity, and what these tests are
     * about is the state machine and the authorisation layer -- the cookie and JWT
     * parsing were verified by hand against the running stack.
     */
    protected function actingAsUser(User $user): static
    {
        return $this->actingAs($user, 'api');
    }
}
