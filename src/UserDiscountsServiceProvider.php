<?php

namespace Hipster\UserDiscounts;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use Hipster\UserDiscounts\Services\DiscountService;
use Hipster\UserDiscounts\Events\DiscountAssigned;
use Hipster\UserDiscounts\Events\DiscountRevoked;
use Hipster\UserDiscounts\Events\DiscountApplied;

class UserDiscountsServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/user-discounts.php',
            'user-discounts'
        );

        $this->app->singleton(DiscountService::class, function ($app) {
            return new DiscountService();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish configuration
        $this->publishes([
            __DIR__.'/../config/user-discounts.php' => config_path('user-discounts.php'),
        ], 'user-discounts-config');

        // Publish migrations
        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'user-discounts-migrations');

        // Load migrations
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                \Hipster\UserDiscounts\Console\TestDiscountCommand::class,
            ]);
        }

        // Register event listeners
        $this->registerEventListeners();
    }

    /**
     * Register event listeners.
     */
    private function registerEventListeners(): void
    {
        // You can add custom event listeners here
        // For example, to clear cache when discounts are assigned/revoked
        
        Event::listen(DiscountAssigned::class, function (DiscountAssigned $event) {
            // Clear user cache when discount is assigned
            app(DiscountService::class)->clearUserCache($event->userId);
        });

        Event::listen(DiscountRevoked::class, function (DiscountRevoked $event) {
            // Clear user cache when discount is revoked
            app(DiscountService::class)->clearUserCache($event->userId);
        });
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            DiscountService::class,
        ];
    }
}
