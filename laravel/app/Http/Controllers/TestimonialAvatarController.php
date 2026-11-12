<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UploadImageRequest;
use App\Models\Testimonial;
use App\Support\Uploads;
use Illuminate\Http\RedirectResponse;

/**
 * Avatar upload for a testimonial.
 *
 * Useful when a reviewer was collected by link and their photo arrives later,
 * or when an imported review needs a face.
 */
class TestimonialAvatarController extends Controller
{
    public function store(UploadImageRequest $request, Testimonial $testimonial): RedirectResponse
    {
        $this->authorize('moderate', $testimonial);

        $path = Uploads::replace(
            $request->file('avatar'),
            Uploads::testimonialDirectory($testimonial->id),
            'avatar',
        );

        $testimonial->update(['author_avatar_url' => Uploads::url($path)]);

        return back()->with('success', 'Avatar uploaded.');
    }

    public function destroy(Testimonial $testimonial): RedirectResponse
    {
        $this->authorize('moderate', $testimonial);

        Uploads::forget(Uploads::testimonialDirectory($testimonial->id), 'avatar');

        $testimonial->update(['author_avatar_url' => null]);

        return back()->with('success', 'Avatar removed.');
    }
}
