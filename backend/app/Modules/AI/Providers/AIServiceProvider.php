<?php

namespace App\Modules\AI\Providers;

use Illuminate\Support\ServiceProvider;
use App\Modules\AI\Services\AIService;
use App\Modules\AI\Services\VectorStoreService;
use App\Modules\AI\Services\ChatbotService;
use App\Modules\AI\Services\AgentService;

class AIServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(AIService::class, function ($app) {
            return new AIService();
        });

        $this->app->singleton(VectorStoreService::class, function ($app) {
            return new VectorStoreService();
        });

        $this->app->singleton(ChatbotService::class, function ($app) {
            return new ChatbotService();
        });

        $this->app->singleton(AgentService::class, function ($app) {
            return new AgentService();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
        $this->loadRoutesFrom(__DIR__ . '/../Routes/api.php');
    }
} 