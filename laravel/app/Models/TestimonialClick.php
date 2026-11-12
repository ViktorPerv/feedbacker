<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One click on a testimonial card, written by the Go widget service.
 *
 * The table is append-only and carries no updated_at, hence $timestamps.
 */
#[Fillable(['space_id', 'testimonial_id', 'referrer', 'clicked_at'])]
class TestimonialClick extends Model
{
    use HasFactory;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'clicked_at' => 'datetime',
        ];
    }

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    public function testimonial(): BelongsTo
    {
        return $this->belongsTo(Testimonial::class);
    }

    public function scopeSince(Builder $query, int $days): Builder
    {
        return $query->where('clicked_at', '>=', now()->subDays($days));
    }
}
