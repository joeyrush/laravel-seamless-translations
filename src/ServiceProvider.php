<?php

namespace JoeyRush\SeamlessTranslations;

use App\SeamlessTranslations\Commands\NewLocale;
use App\SeamlessTranslations\Middleware\CheckLocale;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider as BaseProvider;

class ServiceProvider extends BaseProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        Event::listen(MigrationsEnded::class, function () {
            Cache::forget('schema.tables');
        });
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        $router = $this->app['router'];
        $router->pushMiddlewareToGroup('web', CheckLocale::class);

        $timestamp = date('Y_m_d_His');
        $this->publishes([
            __DIR__.'/migrations/2019_09_29_114636_create_locales_table.php' => database_path("migrations/{$timestamp}_create_locales_table.php")
        ], 'migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                NewLocale::class
            ]);
        }
    }
}
