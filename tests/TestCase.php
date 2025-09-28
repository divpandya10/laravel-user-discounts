<?php

namespace Hipster\UserDiscounts\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Hipster\UserDiscounts\UserDiscountsServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Set up test database
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    protected function getPackageProviders($app)
    {
        return [
            UserDiscountsServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        // Set user model for testing
        $app['config']->set('user-discounts.user_model', 'App\Models\User');
    }
}

