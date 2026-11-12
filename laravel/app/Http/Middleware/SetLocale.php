<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Language;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

/**
 * Выбирает язык для запроса.
 *
 * Порядок приоритета:
 *   1. сессия — явный выбор в переключателе;
 *   2. сохранённая настройка авторизованного пользователя;
 *   3. заголовок браузера Accept-Language;
 *   4. язык, отмеченный как используемый по умолчанию.
 *
 * Сессия имеет наивысший приоритет, чтобы язык авторизованного пользователя
 * менялся сразу, без ожидания сохранения профиля.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolve($request);

        if ($locale !== null) {
            app()->setLocale($locale);
            Carbon::setLocale($locale);
        }

        return $next($request);
    }

    private function resolve(Request $request): ?string
    {
        // Before the first migration there is no language table, and a request
        // should still boot rather than fail on a missing relation.
        if (! Schema::hasTable('languages')) {
            return config('app.locale', 'en');
        }

        $supported = Language::codes();

        if ($supported === []) {
            return config('app.locale', 'en');
        }

        $session = $request->hasSession() ? $request->session()->get('locale') : null;

        if (Language::isSupported($session)) {
            return $session;
        }

        $preferred = $request->user()?->locale;

        if (Language::isSupported($preferred)) {
            return $preferred;
        }

        $fromBrowser = $request->getPreferredLanguage($supported);

        if (Language::isSupported($fromBrowser)) {
            return $fromBrowser;
        }

        return Language::defaultCode();
    }
}
