<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TestimonialStatusEnum;
use Database\Factories\SpaceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Пространство пользователя для сбора и публикации отзывов.
 *
 * @property string $id
 * @property int $user_id
 * @property string $slug
 * @property string $name
 * @property string|null $custom_domain
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read SpaceBranding|null $branding
 * @property-read Collection<int, Testimonial> $testimonials
 * @property-read Collection<int, Widget> $widgets
 */
#[Fillable(['name', 'slug', 'custom_domain', 'is_active'])]
class Space extends Model
{
    /** @use HasFactory<SpaceFactory> */
    use HasFactory, HasUuids;

    /**
     * Возвращает преобразования атрибутов модели.
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
     * Использует публичный slug вместо UUID для привязки модели маршрута.
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
     * Возвращает отзывы, доступные для показа на публичной стене.
     */
    public function approvedTestimonials(): HasMany
    {
        return $this->testimonials()
            ->where('status', TestimonialStatusEnum::Approved->value);
    }

    /**
     * Возвращает оформление пространства или несохранённый экземпляр по умолчанию.
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
     * Возвращает публичный URL страницы сбора отзывов.
     */
    public function collectionUrl(): string
    {
        return url("/c/{$this->slug}");
    }

    /**
     * Создаёт оформление для каждого пространства, чтобы страница всегда отображалась.
     */
    protected static function booted(): void
    {
        static::created(function (Space $space): void {
            $space->branding()->create([]);
        });
    }
}
