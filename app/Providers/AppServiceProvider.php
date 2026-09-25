<?php

namespace App\Providers;

use App\Contracts\Repositories\AssistantPendingActionInterface;
use App\Contracts\Repositories\AssistantRunInterface;
use App\Contracts\Repositories\AssistantUsageInterface;
use App\Contracts\Repositories\ClientAddressInterface;
use App\Contracts\Repositories\ClientInterface;
use App\Contracts\Repositories\ConversationInterface;
use App\Contracts\Repositories\DeliveryInterface;
use App\Contracts\Repositories\MessageInterface;
use App\Contracts\Repositories\InviteInterface;
use App\Contracts\Repositories\LogInterface;
use App\Contracts\Repositories\NotificationInterface;
use App\Contracts\Repositories\RoleInterface;
use App\Contracts\Repositories\UserInterface;
use App\Repositories\AssistantPendingActionRepository;
use App\Repositories\AssistantRunRepository;
use App\Repositories\AssistantUsageRepository;
use App\Repositories\ClientAddressRepository;
use App\Repositories\ClientRepository;
use App\Repositories\ConversationRepository;
use App\Repositories\DeliveryRepository;
use App\Repositories\MessageRepository;
use App\Repositories\InviteRepository;
use App\Repositories\LogRepository;
use App\Repositories\NotificationRepository;
use App\Repositories\RoleRepository;
use App\Repositories\UserRepository;
use App\Assistant\Llm\GeminiLlmClient;
use App\Assistant\Llm\GuardedLlmClient;
use App\Assistant\Llm\LlmClient;
use App\Assistant\Tools\CanCancelDeliveryTool;
use App\Assistant\Tools\GetDeliveryTool;
use App\Assistant\Tools\HandoffToSupportTool;
use App\Assistant\Tools\ListMyDeliveriesTool;
use App\Assistant\Tools\RequestCancelDeliveryTool;
use App\Assistant\ToolRegistry;
use App\Assistant\Support\Clock;
use App\Assistant\Support\Sleeper;
use App\Assistant\Support\SystemClock;
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
        $this->app->singleton(SystemClock::class);
        $this->app->bind(Clock::class, SystemClock::class);
        $this->app->bind(Sleeper::class, SystemClock::class);

        // A singleton: the guard keeps the time of the last call, and the spacing between
        // calls only works if every call goes through the same instance.
        $this->app->singleton(LlmClient::class, function ($app) {
            $provider = config('assistant.provider');
            $settings = config("assistant.providers.{$provider}");

            $inner = match ($provider) {
                'gemini' => new GeminiLlmClient(
                    apiKey: $settings['api_key'],
                    model: config('assistant.model'),
                    baseUrl: $settings['base_url'],
                    timeoutSeconds: config('assistant.timeout_seconds'),
                ),
                default => throw new \InvalidArgumentException("Unknown assistant provider '{$provider}'."),
            };

            return new GuardedLlmClient(
                inner: $inner,
                usage: $app->make(AssistantUsageInterface::class),
                clock: $app->make(Clock::class),
                sleeper: $app->make(Sleeper::class),
                settings: $settings,
                dailyTokenCap: config('assistant.daily_token_cap'),
            );
        });

        $this->app->bind(ToolRegistry::class, fn($app) => new ToolRegistry([
            $app->make(ListMyDeliveriesTool::class),
            $app->make(GetDeliveryTool::class),
            $app->make(CanCancelDeliveryTool::class),
            $app->make(RequestCancelDeliveryTool::class),
            $app->make(HandoffToSupportTool::class),
        ]));

        $this->app->bind(
            AssistantPendingActionInterface::class,
            AssistantPendingActionRepository::class
        );

        $this->app->bind(
            AssistantRunInterface::class,
            AssistantRunRepository::class
        );

        $this->app->bind(
            AssistantUsageInterface::class,
            AssistantUsageRepository::class
        );

        $this->app->bind(
            ClientAddressInterface::class,
            ClientAddressRepository::class
        );

        $this->app->bind(
            ClientInterface::class,
            ClientRepository::class
        );

        $this->app->bind(
            ConversationInterface::class,
            ConversationRepository::class
        );

        $this->app->bind(
            DeliveryInterface::class,
            DeliveryRepository::class
        );

        $this->app->bind(
            MessageInterface::class,
            MessageRepository::class
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
