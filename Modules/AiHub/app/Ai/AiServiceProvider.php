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
        // Registra a AiFactory no contêiner de serviços
        $this->app->singleton(AiFactory::class, function ($app) {
            return new AiFactory;
        });

        // Registra o AiService no contêiner de serviços
        // Ele receberá a AiFactory automaticamente via injeção de dependência
        $this->app->singleton(AiService::class, function ($app) {
            return new AiService($app->make(AiFactory::class));
        });

        // Opcional: Você pode criar um alias para facilitar a injeção ou uso via helper
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
