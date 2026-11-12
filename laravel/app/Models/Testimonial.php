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
use Illuminate\Support\Carbon;

/**
 * Отзыв, отправленный в пространство.
 *
 * @property string $id
 * @property string $space_id
 * @property string $author_name
 * @property string|null $author_email
 * @property string|null $author_avatar_url
 * @property string|null $author_title
 * @property string|null $author_company
 * @property int $rating
 * @property string $content
 * @property string|null $video_url
 * @property TestimonialStatusEnum $status
 * @property array<int, string>|null $tags
 * @property bool $is_featured
 * @property Carbon|null $approved_at
 * @property string $source
 * @property string|null $imported_from_url
 * @property string|null $search_vector
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Space $space
 */
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

    /** Максимальное количество тегов у одного отзыва. */
    public const MAX_TAGS = 10;

    /** Максимальная длина одного тега. */
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
     * Сортирует отзывы для публичной стены: сначала избранные, затем новые.
     */
    public function scopeForWall(Builder $query): Builder
    {
        return $query->orderByDesc('is_featured')->orderByDesc('created_at');
    }

    /**
     * Выполняет полнотекстовый поиск по вычисляемому полю `search_vector`.
     *
     * Если строка не содержит пригодных токенов, запрос остаётся без изменений.
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
     * Сортирует результаты scopeSearch по релевантности.
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
     * Отбирает отзывы по одному тегу.
     *
     * Поле `tags` хранится как JSON, поэтому оператору вхождения требуется
     * преобразование в jsonb и используется сырой запрос вместо whereJsonContains().
     */
    public function scopeTaggedWith(Builder $query, string $tag): Builder
    {
        return $query->whereRaw('tags::jsonb @> ?::jsonb', [
            json_encode([$tag]),
        ]);
    }

    /**
     * Преобразует текст в tsquery: каждая буквенно-цифровая часть становится
     * приведённым к нижнему регистру префиксом, объединённым через AND.
     *
     * Возвращает null, если пригодных частей нет, чтобы вызывающий код пропустил
     * условие: `to_tsquery('')` вызывает синтаксическую ошибку PostgreSQL.
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
     * Нормализует теги: обрезает пробелы, меняет регистр и удаляет дубликаты.
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
     * Возвращает инициалы для заглушки аватара при отсутствии изображения.
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
