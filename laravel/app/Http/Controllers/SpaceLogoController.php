<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UploadImageRequest;
use App\Models\Space;
use App\Support\Uploads;
use Illuminate\Http\RedirectResponse;

/**
 * Logo upload for a space's collection page and widgets.
 *
 * The stored value stays a URL, so a pasted link and an upload are
 * interchangeable — the branding form offers both.
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
