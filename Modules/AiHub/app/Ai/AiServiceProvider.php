<?php

namespace Modules\AiHub\Ai;

use Illuminate\Support\ServiceProvider;
use Modules\AiHub\Ai\Factory\AiFactory;

class AiServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register the AiFactory in the service container
        $this->app->singleton(AiFactory::class, function ($app) {
            return new AiFactory;
        });

        // Register the AiService in the service container
        // It will receive the AiFactory automatically via dependency injection
        $this->app->singleton(AiService::class, function ($app) {
            return new AiService($app->make(AiFactory::class));
        });

        // Optional: You can create an alias to facilitate injection or use via helper
        // $this->app->alias(AiService::class, 'ai');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
