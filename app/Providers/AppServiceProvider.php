<?php

namespace App\Providers;

use App\Contracts\Repositories\ClientAddressInterface;
use App\Contracts\Repositories\ClientInterface;
use App\Contracts\Repositories\DeliveryInterface;
use App\Contracts\Repositories\InviteInterface;
use App\Contracts\Repositories\LogInterface;
use App\Contracts\Repositories\NotificationInterface;
use App\Contracts\Repositories\RoleInterface;
use App\Contracts\Repositories\UserInterface;
use App\Repositories\ClientAddressRepository;
use App\Repositories\ClientRepository;
use App\Repositories\DeliveryRepository;
use App\Repositories\InviteRepository;
use App\Repositories\LogRepository;
use App\Repositories\NotificationRepository;
use App\Repositories\RoleRepository;
use App\Repositories\UserRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(
            ClientAddressInterface::class,
            ClientAddressRepository::class
        );

        $this->app->bind(
            ClientInterface::class,
            ClientRepository::class
        );

        $this->app->bind(
            DeliveryInterface::class,
            DeliveryRepository::class
        );

        $this->app->bind(
            InviteInterface::class,
            InviteRepository::class
        );

        $this->app->bind(
            LogInterface::class,
            LogRepository::class
        );

        $this->app->bind(
            NotificationInterface::class,
            NotificationRepository::class
        );

        $this->app->bind(
            RoleInterface::class,
            RoleRepository::class
        );

        $this->app->bind(
            UserInterface::class,
            UserRepository::class
        );
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
