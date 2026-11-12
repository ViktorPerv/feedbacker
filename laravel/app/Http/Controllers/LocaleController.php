<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Language;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Переключатель языка.
 *
 * Доступен без аутентификации, чтобы публичную страницу можно было читать на
 * любом языке. Выбор авторизованного пользователя сохраняется в профиле и
 * применяется в других браузерах.
 */
class LocaleController extends Controller
{
    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        abort_unless(Language::isSupported($locale), 404);

        $request->session()->put('locale', $locale);

        $user = $request->user();

        if ($user !== null && $user->locale !== $locale) {
            $user->forceFill(['locale' => $locale])->save();
        }

        return back();
    }
}
