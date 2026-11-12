<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for a submission coming from the public collection page.
 *
 * There is no authenticated user here, so authorize() is open and abuse is
 * handled by the throttle middleware plus the honeypot check.
 */
class StorePublicTestimonialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'author_name' => ['required', 'string', 'max:255'],
            'author_email' => ['nullable', 'email', 'max:255'],
            'author_title' => ['nullable', 'string', 'max:255'],
            'author_company' => ['nullable', 'string', 'max:255'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'content' => ['required', 'string', 'min:10', 'max:2000'],
            'author_avatar_url' => ['nullable', 'url:http,https', 'max:2048'],
            // Restricted to http/https: the widget turns this into an iframe or
            // a <video> source, and other schemes have no business there.
            'video_url' => ['nullable', 'url:http,https', 'max:2048'],

            // Honeypot: a real person never fills this in. Accepted by the
            // validator so the controller can silently drop the submission
            // instead of telling a bot what tripped it.
            'website' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'content.min' => 'Please write at least :min characters.',
            'rating.between' => 'Please choose a rating between 1 and 5.',
        ];
    }

    /**
     * Whether the honeypot field was filled in.
     */
    public function looksLikeSpam(): bool
    {
        return filled($this->input('website'));
    }

    /**
     * Only the fields a submitter is allowed to set. Status, tags, featured
     * state and provenance are controlled by the server.
     *
     * @return array<string, mixed>
     */
    public function testimonialData(): array
    {
        return [
            'author_name' => $this->validated('author_name'),
            'author_email' => $this->validated('author_email'),
            'author_title' => $this->validated('author_title'),
            'author_company' => $this->validated('author_company'),
            'author_avatar_url' => $this->validated('author_avatar_url'),
            'rating' => $this->validated('rating'),
            'content' => $this->validated('content'),
            'video_url' => $this->validated('video_url'),
        ];
    }
}
