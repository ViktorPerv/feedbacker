<?php

namespace App\Providers;

use App\Translation\DatabaseLoader;
use App\Translation\DatabaseTranslator;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->bindDatabaseTranslationLoader();

        // Behind the Docker proxy every request arrives as HTTP; without this
        // generated URLs would downgrade and break asset loading over HTTPS.
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }

    /**
     * Teach the translator to read from the database.
     *
     * Two halves, and both are needed:
     *
     *  - the loader reads rows and merges them over the language files, so a
     *    key with no row still resolves;
     *  - the translator drops its own memo when the catalogue changes, because
     *    it would otherwise never ask the loader twice in one request.
     */
    private function bindDatabaseTranslationLoader(): void
    {
        $this->app->extend('translation.loader', function ($loader, $app) {
            return new DatabaseLoader($app['files'], $app['path.lang']);
        });

        $this->app->extend('translator', function ($translator, $app) {
            return new DatabaseTranslator(
                $translator->getLoader(),
                $translator->getLocale(),
                $translator->getFallback(),
            );
        });
    }
}
