<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TestimonialStatusEnum;
use Database\Factories\TestimonialFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'space_id',
    'author_name',
    'author_email',
    'author_avatar_url',
    'author_title',
    'author_company',
    'rating',
    'content',
    'video_url',
    'status',
    'tags',
    'is_featured',
    'source',
    'imported_from_url',
])]
class Testimonial extends Model
{
    /** @use HasFactory<TestimonialFactory> */
    use HasFactory, HasUuids;

    /** The most tags a single testimonial may carry. */
    public const MAX_TAGS = 10;

    /** The longest a single tag may be. */
    public const MAX_TAG_LENGTH = 32;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'status' => TestimonialStatusEnum::class,
            'tags' => 'array',
            'is_featured' => 'boolean',
            'approved_at' => 'datetime',
        ];
    }

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    // ── Scopes ──

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', TestimonialStatusEnum::Approved->value);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', TestimonialStatusEnum::Pending->value);
    }

    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', TestimonialStatusEnum::Rejected->value);
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    /**
     * Ordering used on every public wall: featured first, then newest.
     */
    public function scopeForWall(Builder $query): Builder
    {
        return $query->orderByDesc('is_featured')->orderByDesc('created_at');
    }

    /**
     * Full-text match against the generated `search_vector` column.
     *
     * A term that produces no usable tokens (punctuation only) leaves the
     * query untouched rather than erroring.
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        $tsquery = self::toTsquery($term);

        if ($tsquery === null) {
            return $query;
        }

        return $query->whereRaw(
            "search_vector @@ to_tsquery('simple', ?)",
            [$tsquery],
        );
    }

    /**
     * Rank the results of scopeSearch by relevance.
     */
    public function scopeRankedBy(Builder $query, string $term): Builder
    {
        $tsquery = self::toTsquery($term);

        if ($tsquery === null) {
            return $query;
        }

        return $query->orderByRaw(
            "ts_rank(search_vector, to_tsquery('simple', ?)) DESC",
            [$tsquery],
        );
    }

    /**
     * Match a single tag.
     *
     * `tags` is a json column, so the containment operator needs the jsonb
     * cast; this is why the raw form is used instead of whereJsonContains().
     */
    public function scopeTaggedWith(Builder $query, string $tag): Builder
    {
        return $query->whereRaw('tags::jsonb @> ?::jsonb', [
            json_encode([$tag]),
        ]);
    }

    /**
     * Turn free text into a tsquery: every alphanumeric run becomes a
     * lowercased prefix term, ANDed together.
     *
     * Returns null when nothing usable remains, so callers can skip the
     * clause entirely — `to_tsquery('')` is a syntax error in PostgreSQL.
     */
    public static function toTsquery(string $term): ?string
    {
        $terms = preg_split('/[^\p{L}\p{N}]+/u', $term, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($terms === []) {
            return null;
        }

        return collect($terms)
            ->map(fn (string $part) => mb_strtolower($part).':*')
            ->implode(' & ');
    }

    /**
     * Normalise a submitted tag list: trimmed, lowercased, deduplicated.
     *
     * @param  array<int, string>  $tags
     * @return array<int, string>
     */
    public static function normaliseTags(array $tags): array
    {
        return collect($tags)
            ->map(fn ($tag) => mb_strtolower(trim((string) $tag)))
            ->filter()
            ->unique()
            ->take(self::MAX_TAGS)
            ->values()
            ->all();
    }

    // ── Moderation ──

    public function approve(): void
    {
        $this->forceFill([
            'status' => TestimonialStatusEnum::Approved,
            'approved_at' => $this->approved_at ?? now(),
        ])->save();
    }

    public function reject(): void
    {
        $this->forceFill([
            'status' => TestimonialStatusEnum::Rejected,
            'is_featured' => false,
        ])->save();
    }

    public function toggleFeatured(): void
    {
        $this->forceFill(['is_featured' => ! $this->is_featured])->save();
    }

    public function isApproved(): bool
    {
        return $this->status === TestimonialStatusEnum::Approved;
    }

    // ── Presentation helpers ──

    /**
     * Initials used as an avatar placeholder when no image is available.
     */
    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->author_name)) ?: [];

        $initials = collect($parts)
            ->filter()
            ->take(2)
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');

        return $initials !== '' ? $initials : '?';
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->space?->user_id === $user->id;
    }
}
