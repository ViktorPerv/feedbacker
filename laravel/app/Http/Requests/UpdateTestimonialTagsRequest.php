<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Testimonial;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTestimonialTagsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Drop blank entries before the rules run.
     *
     * A tag input naturally emits an empty string when the user clears it, and
     * ConvertEmptyStringsToNull turns that into null — which the `string` rule
     * then rejects, failing the whole request over one stray entry. Blanks are
     * filtered again after validation, so this only decides what is *checked*.
     */
    protected function prepareForValidation(): void
    {
        $tags = $this->input('tags');

        if (! is_array($tags)) {
            return;
        }

        $this->merge([
            'tags' => array_values(array_filter(
                $tags,
                fn ($tag) => is_string($tag) && trim($tag) !== '',
            )),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // `present` rather than `required`: sending an empty array is how a
            // caller clears every tag.
            'tags' => ['present', 'array', 'max:'.Testimonial::MAX_TAGS],
            'tags.*' => ['string', 'max:'.Testimonial::MAX_TAG_LENGTH, 'distinct'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tags.max' => 'A testimonial can carry at most :max tags.',
            'tags.*.max' => 'Tags must be :max characters or fewer.',
            'tags.*.distinct' => 'Each tag may only appear once.',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function normalisedTags(): array
    {
        return Testimonial::normaliseTags((array) $this->validated('tags'));
    }
}
