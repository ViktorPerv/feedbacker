<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\WidgetLayoutEnum;
use App\Http\Requests\WidgetRequest;
use App\Models\Space;
use App\Models\Widget;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The widget builder: several named embeds per space, each with its own layout
 * and configuration.
 */
class WidgetController extends Controller
{
    public function index(Space $space): Response
    {
        $this->authorize('view', $space);

        $widgets = $space->widgets()
            ->latest()
            ->get()
            ->map(fn (Widget $widget) => [
                'id' => $widget->id,
                'name' => $widget->name,
                'layout' => $widget->layout->value,
                'layout_label' => $widget->layout->label(),
                'token' => $widget->token,
                'is_active' => $widget->is_active,
                'embed_url' => $widget->embedUrl(),
                'embed_snippet' => $widget->embedSnippet(),
                'config' => $widget->configWithDefaults(),
                'created_at' => $widget->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Spaces/Widgets/Index', [
            'space' => [
                'id' => $space->id,
                'name' => $space->name,
                'slug' => $space->slug,
            ],
            'widgets' => $widgets,
            'can' => [
                'create' => request()->user()->can('update', $space),
            ],
        ]);
    }

    public function create(Space $space): Response
    {
        $this->authorize('update', $space);

        return Inertia::render('Spaces/Widgets/Create', [
            'space' => [
                'id' => $space->id,
                'name' => $space->name,
                'slug' => $space->slug,
            ],
            'layouts' => WidgetLayoutEnum::options(),
        ]);
    }

    public function store(WidgetRequest $request, Space $space): RedirectResponse
    {
        $this->authorize('update', $space);

        $widget = $space->widgets()->create([
            'name' => $request->validated('name'),
            'layout' => $request->validated('layout'),
            'config' => $request->configData(),
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('spaces.widgets.edit', [$space->slug, $widget->token])
            ->with('success', 'Widget created — copy the snippet when you are happy with the preview.');
    }

    public function edit(Space $space, Widget $widget): Response
    {
        $this->authorize('update', $widget);

        return Inertia::render('Spaces/Widgets/Edit', [
            'space' => [
                'id' => $space->id,
                'name' => $space->name,
                'slug' => $space->slug,
            ],
            'widget' => [
                'id' => $widget->id,
                'name' => $widget->name,
                'layout' => $widget->layout->value,
                'token' => $widget->token,
                'is_active' => $widget->is_active,
                'config' => $widget->configWithDefaults(),
                'embed_url' => $widget->embedUrl(),
                'embed_snippet' => $widget->embedSnippet(),
            ],
            'layouts' => WidgetLayoutEnum::options(),
            'can' => [
                'delete' => request()->user()->can('delete', $widget),
            ],
        ]);
    }

    public function update(WidgetRequest $request, Space $space, Widget $widget): RedirectResponse
    {
        $this->authorize('update', $widget);

        $widget->update([
            'name' => $request->validated('name'),
            'layout' => $request->validated('layout'),
            'config' => $request->configData(),
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('spaces.widgets.edit', [$space->slug, $widget->token])
            ->with('success', 'Widget saved.');
    }

    public function destroy(Space $space, Widget $widget): RedirectResponse
    {
        $this->authorize('delete', $widget);

        $widget->delete();

        return redirect()
            ->route('spaces.widgets.index', $space->slug)
            ->with('success', 'Widget deleted.');
    }
}
