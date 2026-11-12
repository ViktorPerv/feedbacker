<?php

namespace App\Http\Middleware;

use App\Models\Language;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

/**
 * Picks the locale for the request.
 *
 * Order of precedence:
 *   1. the session — an explicit choice from the switcher
 *   2. the signed-in user's saved preference
 *   3. the browser's Accept-Language header
 *   4. the language marked as default
 *
 * The session comes first so that switching language takes effect immediately
 * for a signed-in user too, without waiting for a profile save.
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
