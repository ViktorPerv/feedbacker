<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UploadImageRequest;
use App\Models\Space;
use App\Support\Uploads;
use Illuminate\Http\RedirectResponse;

/**
 * Загрузка логотипа для страницы сбора отзывов и виджетов пространства.
 *
 * Сохраняется URL, поэтому вставленная ссылка и загруженный файл взаимозаменяемы.
 */
class SpaceLogoController extends Controller
{
    public function store(UploadImageRequest $request, Space $space): RedirectResponse
    {
        $this->authorize('update', $space);

        $path = Uploads::replace(
            $request->file('logo'),
            Uploads::spaceDirectory($space->id),
            'logo',
        );

        $space->branding()->updateOrCreate(
            ['space_id' => $space->id],
            ['logo_url' => Uploads::url($path)],
        );

        return back()->with('success', 'Logo uploaded.');
    }

    public function destroy(Space $space): RedirectResponse
    {
        $this->authorize('update', $space);

        Uploads::forget(Uploads::spaceDirectory($space->id), 'logo');

        $space->branding()->updateOrCreate(
            ['space_id' => $space->id],
            ['logo_url' => null],
        );

        return back()->with('success', 'Logo removed.');
    }
}
