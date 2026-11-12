<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PermissionEnum;
use App\Enums\TestimonialStatusEnum;
use App\Http\Requests\StoreSpaceRequest;
use App\Http\Requests\UpdateSpaceRequest;
use App\Models\Space;
use App\Support\Analytics;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SpaceController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Space::class);

        $user = $request->user();
        $canViewAll = $user->checkPermissionTo(PermissionEnum::SpacesViewAny->value);

        // Accounts with oversight permissions get an overview of every space;
        // everyone else only ever sees their own.
        $spaces = ($canViewAll ? Space::query()->with('user') : $user->spaces())
            ->withCount([
                'testimonials',
                'testimonials as pending_count' => fn ($query) => $query
                    ->where('status', TestimonialStatusEnum::Pending->value),
            ])
            ->latest()
            ->get()
            ->map(fn (Space $space) => [
                'id' => $space->id,
                'name' => $space->name,
                'slug' => $space->slug,
                'custom_domain' => $space->custom_domain,
                'is_active' => $space->is_active,
                'owner_name' => $space->user_id !== $user->id
                    ? $space->user?->name
                    : null,
                'testimonials_count' => $space->testimonials_count,
                'pending_count' => $space->pending_count,
                'collection_url' => $space->collectionUrl(),
                'created_at' => $space->created_at?->toIso8601String(),
                'can' => [
                    'update' => $user->can('update', $space),
                    'delete' => $user->can('delete', $space),
                    'moderate' => $user->can('moderateTestimonials', $space),
                ],
            ]);

        return Inertia::render('Spaces/Index', [
            'spaces' => $spaces,
            'canViewAll' => $canViewAll,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Space::class);

        return Inertia::render('Spaces/Create');
    }

    public function store(StoreSpaceRequest $request): RedirectResponse
    {
        $space = $request->user()->spaces()->create([
            'name' => $request->validated('name'),
            'slug' => $request->validated('slug'),
            'custom_domain' => $request->validated('custom_domain'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()
            ->route('spaces.show', $space)
            ->with('success', 'Space created successfully.');
    }

    public function show(Space $space): Response
    {
        $this->authorize('view', $space);

        $user = request()->user();
        $space->load('branding');

        return Inertia::render('Spaces/Show', [
            'space' => [
                'id' => $space->id,
                'name' => $space->name,
                'slug' => $space->slug,
                'custom_domain' => $space->custom_domain,
                'is_active' => $space->is_active,
                'collection_url' => $space->collectionUrl(),
                'embed_url' => url("/w/{$space->slug}.js"),
                'embed_snippet' => sprintf(
                    "<div data-feedbacker=\"%s\"></div>\n<script async src=\"%s\"></script>",
                    $space->slug,
                    url("/w/{$space->slug}.js"),
                ),
                'created_at' => $space->created_at?->toIso8601String(),
                'testimonials_count' => $space->testimonials()->count(),
                'pending_count' => $space->testimonials()
                    ->where('status', TestimonialStatusEnum::Pending->value)
                    ->count(),
                'branding' => $space->brandingOrDefault()->only([
                    'primary_color',
                    'background_color',
                    'font_family',
                    'button_text',
                    'thank_you_message',
                    'logo_url',
                ]),
            ],
            'analytics' => Analytics::summary(collect([$space->id])),
            'widgetsCount' => $space->widgets()->count(),
            'can' => [
                'update' => $user->can('update', $space),
                'delete' => $user->can('delete', $space),
                'moderate' => $user->can('moderateTestimonials', $space),
            ],
        ]);
    }

    public function edit(Space $space): Response
    {
        $this->authorize('update', $space);

        return Inertia::render('Spaces/Edit', [
            'space' => [
                'id' => $space->id,
                'name' => $space->name,
                'slug' => $space->slug,
                'custom_domain' => $space->custom_domain,
                'is_active' => $space->is_active,
            ],
        ]);
    }

    public function update(UpdateSpaceRequest $request, Space $space): RedirectResponse
    {
        $space->update([
            'name' => $request->validated('name'),
            'slug' => $request->validated('slug'),
            'custom_domain' => $request->validated('custom_domain'),
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('spaces.show', $space)
            ->with('success', 'Space updated successfully.');
    }

    public function destroy(Space $space): RedirectResponse
    {
        $this->authorize('delete', $space);

        $space->delete();

        return redirect()
            ->route('spaces.index')
            ->with('success', 'Space deleted.');
    }
}
