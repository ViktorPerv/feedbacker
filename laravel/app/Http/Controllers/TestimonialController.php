<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\TestimonialStatusEnum;
use App\Http\Requests\UpdateTestimonialTagsRequest;
use App\Models\Space;
use App\Models\Testimonial;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Moderation inbox for a single space.
 */
class TestimonialController extends Controller
{
    public function index(Request $request, Space $space): Response
    {
        $this->authorize('moderateTestimonials', $space);

        $status = $request->string('status')->toString();
        $search = trim($request->string('search')->toString());
        $tag = $request->string('tag')->toString();
        $rating = $request->integer('rating');

        $statusIsValid = in_array($status, TestimonialStatusEnum::values(), true);
        $tsquery = $search !== '' ? Testimonial::toTsquery($search) : null;

        $testimonials = $space->testimonials()
            ->when($statusIsValid, fn ($query) => $query->where('status', $status))
            ->when($tsquery !== null, fn ($query) => $query->search($search))
            ->when($tag !== '', fn ($query) => $query->taggedWith($tag))
            ->when($rating >= 1 && $rating <= 5, fn ($query) => $query->where('rating', $rating))
            // Relevance wins when searching; otherwise newest first.
            ->when(
                $tsquery !== null,
                fn ($query) => $query->rankedBy($search)->latest(),
                fn ($query) => $query->latest(),
            )
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Testimonials/Index', [
            'space' => [
                'id' => $space->id,
                'name' => $space->name,
                'slug' => $space->slug,
                'collection_url' => $space->collectionUrl(),
            ],
            'testimonials' => $testimonials->through(fn (Testimonial $testimonial) => [
                'id' => $testimonial->id,
                'author_name' => $testimonial->author_name,
                'author_email' => $testimonial->author_email,
                'author_title' => $testimonial->author_title,
                'author_company' => $testimonial->author_company,
                'author_avatar_url' => $testimonial->author_avatar_url,
                'initials' => $testimonial->initials(),
                'rating' => $testimonial->rating,
                'content' => $testimonial->content,
                'status' => $testimonial->status->value,
                'status_label' => $testimonial->status->label(),
                'is_featured' => $testimonial->is_featured,
                'video_url' => $testimonial->video_url,
                'tags' => $testimonial->tags ?? [],
                'source' => $testimonial->source,
                'created_at' => $testimonial->created_at?->toIso8601String(),
                'approved_at' => $testimonial->approved_at?->toIso8601String(),
            ]),
            'filters' => [
                'status' => $status,
                'search' => $search,
                'tag' => $tag,
                'rating' => $rating ?: null,
            ],
            'counts' => [
                'all' => $space->testimonials()->count(),
                'pending' => $space->testimonials()->where('status', TestimonialStatusEnum::Pending->value)->count(),
                'approved' => $space->testimonials()->where('status', TestimonialStatusEnum::Approved->value)->count(),
                'rejected' => $space->testimonials()->where('status', TestimonialStatusEnum::Rejected->value)->count(),
            ],
            // Every tag in use in this space, so the filter can offer them
            // rather than asking the user to remember spelling.
            'availableTags' => $this->availableTags($space),
            'statuses' => collect(TestimonialStatusEnum::cases())
                ->map(fn (TestimonialStatusEnum $case) => [
                    'value' => $case->value,
                    'label' => $case->label(),
                ])
                ->all(),
        ]);
    }

    /**
     * @return array<int, array{tag: string, count: number}>
     */
    private function availableTags(Space $space): array
    {
        return $space->testimonials()
            ->whereNotNull('tags')
            ->pluck('tags')
            ->flatMap(fn ($tags) => (array) $tags)
            ->filter()
            ->countBy()
            ->sortDesc()
            ->map(fn (int $count, string $tag) => ['tag' => $tag, 'count' => $count])
            ->values()
            ->all();
    }

    public function tags(UpdateTestimonialTagsRequest $request, Testimonial $testimonial): RedirectResponse
    {
        $this->authorize('moderate', $testimonial);

        $testimonial->update(['tags' => $request->normalisedTags()]);

        return back()->with('success', 'Tags updated.');
    }

    public function approve(Testimonial $testimonial): RedirectResponse
    {
        $this->authorize('moderate', $testimonial);

        $testimonial->approve();

        return back()->with('success', 'Testimonial approved.');
    }

    public function reject(Testimonial $testimonial): RedirectResponse
    {
        $this->authorize('moderate', $testimonial);

        $testimonial->reject();

        return back()->with('success', 'Testimonial rejected.');
    }

    public function feature(Testimonial $testimonial): RedirectResponse
    {
        $this->authorize('moderate', $testimonial);

        $testimonial->toggleFeatured();

        return back()->with('success', $testimonial->is_featured
            ? 'Testimonial featured.'
            : 'Testimonial unfeatured.');
    }

    public function destroy(Testimonial $testimonial): RedirectResponse
    {
        $this->authorize('delete', $testimonial);

        $space = $testimonial->space;
        $testimonial->delete();

        return redirect()
            ->route('spaces.testimonials.index', $space->slug)
            ->with('success', 'Testimonial deleted.');
    }
}
