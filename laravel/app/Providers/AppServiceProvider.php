<?php

declare(strict_types=1);

namespace App\Providers;

use App\Translation\DatabaseLoader;
use App\Translation\DatabaseTranslator;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Регистрирует сервисы приложения.
     */
    public function register(): void
    {
        //
    }

    /**
     * Инициализирует сервисы приложения.
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
     * Настраивает чтение переводов из базы данных.
     *
     * Настройка состоит из двух обязательных частей:
     *
     *  - загрузчик читает строки и объединяет их с языковыми файлами, поэтому
     *    ключ без строки в БД продолжает работать;
     *  - переводчик очищает внутренний кеш при изменении каталога, иначе в
     *    рамках одного запроса он не обратится к загрузчику повторно.
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
