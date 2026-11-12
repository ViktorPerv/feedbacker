<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for an uploaded image.
 *
 * SVG is deliberately absent from the allowed types: an SVG can carry script,
 * and these files are served from a bucket that browsers load directly.
 */
class UploadImageRequest extends FormRequest
{
    public const MAX_KILOBYTES = 2048;

    public const MAX_DIMENSION = 2400;

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
            $this->fileField() => [
                'required',
                'file',
                'image',
                'mimes:png,jpg,jpeg,webp',
                'max:'.self::MAX_KILOBYTES,
                // A decompression bomb passes the size check but not this one.
                'dimensions:max_width='.self::MAX_DIMENSION.',max_height='.self::MAX_DIMENSION,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            $this->fileField().'.mimes' => 'Upload a PNG, JPG or WebP image.',
            $this->fileField().'.max' => 'Images must be 2 MB or smaller.',
            $this->fileField().'.dimensions' => 'Images must be at most 2400×2400 pixels.',
        ];
    }

    /**
     * Which field this request is validating.
     *
     * Both the logo and the avatar endpoints share this class, so the field
     * name comes from the route rather than being hard-coded.
     */
    private function fileField(): string
    {
        return $this->routeIs('spaces.logo.store') ? 'logo' : 'avatar';
    }
}
