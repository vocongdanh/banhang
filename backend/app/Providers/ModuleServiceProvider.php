<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\File;

class ModuleServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $modules = File::directories(app_path('Modules'));
        
        foreach ($modules as $module) {
            $moduleName = basename($module);
            
            // Register module service provider
            $provider = "App\\Modules\\{$moduleName}\\Providers\\{$moduleName}ServiceProvider";
            if (class_exists($provider)) {
                $this->app->register($provider);
            }
            
            // Load module routes
            $routesPath = app_path("Modules/{$moduleName}/Routes/api.php");
            if (File::exists($routesPath)) {
                $this->loadRoutesFrom($routesPath);
            }
            
            // Load module migrations
            $migrationsPath = app_path("Modules/{$moduleName}/Database/Migrations");
            if (File::exists($migrationsPath)) {
                $this->loadMigrationsFrom($migrationsPath);
            }
            
            // Load module views
            $viewsPath = app_path("Modules/{$moduleName}/Resources/views");
            if (File::exists($viewsPath)) {
                $this->loadViewsFrom($viewsPath, strtolower($moduleName));
            }
            
            // Load module translations
            $langPath = app_path("Modules/{$moduleName}/Resources/lang");
            if (File::exists($langPath)) {
                $this->loadTranslationsFrom($langPath, strtolower($moduleName));
            }
        }
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
} 