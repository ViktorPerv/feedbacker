<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Language;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Language switcher.
 *
 * Deliberately unauthenticated so the public collection page can be read in
 * either language. A signed-in user's choice is also written to their profile
 * so it follows them to another browser.
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
