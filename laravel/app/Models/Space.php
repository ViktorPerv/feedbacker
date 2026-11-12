<?php

namespace App\Models;

use App\Enums\TestimonialStatusEnum;
use Database\Factories\SpaceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['name', 'slug', 'custom_domain', 'is_active'])]
class Space extends Model
{
    /** @use HasFactory<SpaceFactory> */
    use HasFactory, HasUuids;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Route model binding uses the public slug instead of the UUID.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branding(): HasOne
    {
        return $this->hasOne(SpaceBranding::class);
    }

    public function testimonials(): HasMany
    {
        return $this->hasMany(Testimonial::class);
    }

    public function widgets(): HasMany
    {
        return $this->hasMany(Widget::class);
    }

    /**
     * Testimonials that may be shown on the public wall.
     */
    public function approvedTestimonials(): HasMany
    {
        return $this->testimonials()
            ->where('status', TestimonialStatusEnum::Approved->value);
    }

    /**
     * Branding for this space, falling back to an unsaved default instance.
     */
    public function brandingOrDefault(): SpaceBranding
    {
        return $this->branding ?? new SpaceBranding([
            'primary_color' => '#4F46E5',
            'background_color' => '#FFFFFF',
            'font_family' => 'Inter',
            'button_text' => 'Leave a testimonial',
            'thank_you_message' => 'Thank you for your feedback!',
        ]);
    }

    /**
     * The public collection URL for this space.
     */
    public function collectionUrl(): string
    {
        return url("/c/{$this->slug}");
    }

    /**
     * Every space gets a branding record so the collection page always renders.
     */
    protected static function booted(): void
    {
        static::created(function (Space $space): void {
            $space->branding()->create([]);
        });
    }
}
