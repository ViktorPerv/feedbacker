<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Language;
use App\Models\Translation;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * Корневой шаблон, загружаемый при первом посещении страницы.
     */
    protected $rootView = 'app';

    /**
     * Определяет текущую версию ресурсов.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Определяет свойства, передаваемые клиенту по умолчанию.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
                'can' => [
                    'manageTranslations' => (bool) $request->user()?->can('translations.manage'),
                ],
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'locale' => fn () => app()->getLocale(),
            'locales' => fn () => Language::options(),
            // Only the `app` group travels: it is the interface's own wording,
            // and shipping every framework string would bloat each response.
            'translations' => fn () => Translation::forFrontend('app'),
        ];
    }
}
