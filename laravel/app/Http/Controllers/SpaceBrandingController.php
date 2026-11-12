<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSpaceBrandingRequest;
use App\Models\Space;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SpaceBrandingController extends Controller
{
    public function edit(Space $space): Response
    {
        $this->authorize('update', $space);

        $branding = $space->brandingOrDefault();

        return Inertia::render('Spaces/Branding', [
            'space' => [
                'id' => $space->id,
                'name' => $space->name,
                'slug' => $space->slug,
            ],
            'branding' => $branding->only([
                'logo_url',
                'primary_color',
                'background_color',
                'font_family',
                'button_text',
                'thank_you_message',
            ]),
        ]);
    }

    public function update(UpdateSpaceBrandingRequest $request, Space $space): RedirectResponse
    {
        $space->branding()->updateOrCreate(
            ['space_id' => $space->id],
            $request->validated(),
        );

        return redirect()
            ->route('spaces.branding.edit', $space)
            ->with('success', 'Branding saved.');
    }
}
