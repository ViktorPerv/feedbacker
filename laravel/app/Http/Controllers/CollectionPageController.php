<?php

namespace App\Http\Controllers;

use App\Enums\TestimonialStatusEnum;
use App\Http\Requests\StorePublicTestimonialRequest;
use App\Models\Space;
use App\Notifications\NewTestimonialNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public, unauthenticated testimonial collection page.
 */
class CollectionPageController extends Controller
{
    public function show(Space $space): Response
    {
        $branding = $space->brandingOrDefault();

        return Inertia::render('Collect/Show', [
            'space' => [
                'name' => $space->name,
                'slug' => $space->slug,
                'is_active' => $space->is_active,
            ],
            'branding' => [
                'logo_url' => $branding->logo_url,
                'primary_color' => $branding->primary_color,
                'background_color' => $branding->background_color,
                'font_family' => $branding->font_family,
                'button_text' => $branding->button_text,
                'thank_you_message' => $branding->thank_you_message,
            ],
            'stats' => [
                'approved_count' => $space->testimonials()
                    ->where('status', TestimonialStatusEnum::Approved->value)
                    ->count(),
                'average_rating' => round((float) $space->testimonials()
                    ->where('status', TestimonialStatusEnum::Approved->value)
                    ->avg('rating'), 1),
            ],
        ]);
    }

    public function store(StorePublicTestimonialRequest $request, Space $space): RedirectResponse
    {
        if (! $space->is_active) {
            return back()->with('error', 'This space is not accepting testimonials right now.');
        }

        // Silently accept honeypot hits so bots do not learn what tripped them.
        if ($request->looksLikeSpam()) {
            return redirect()
                ->route('collect.show', $space->slug)
                ->with('success', $space->brandingOrDefault()->thank_you_message);
        }

        $testimonial = $space->testimonials()->create([
            ...$request->testimonialData(),
            'status' => TestimonialStatusEnum::Pending,
            'source' => 'direct',
        ]);

        $this->notifyOwner($testimonial->load('space.user'));

        return redirect()
            ->route('collect.show', $space->slug)
            ->with('success', $space->brandingOrDefault()->thank_you_message);
    }

    /**
     * Queue a heads-up to the space owner.
     *
     * Wrapped in a guard because a submission must succeed even if the
     * notification cannot be dispatched.
     */
    private function notifyOwner(\App\Models\Testimonial $testimonial): void
    {
        $owner = $testimonial->space?->user;

        if ($owner === null || blank($owner->email)) {
            return;
        }

        try {
            Notification::send($owner, new NewTestimonialNotification($testimonial));
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
