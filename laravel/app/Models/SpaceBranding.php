<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Настройки внешнего вида пространства.
 *
 * @property string $id
 * @property string $space_id
 * @property string|null $logo_url
 * @property string $primary_color
 * @property string $background_color
 * @property string $font_family
 * @property string $button_text
 * @property string $thank_you_message
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Space $space
 */
#[Fillable([
    'space_id',
    'logo_url',
    'primary_color',
    'background_color',
    'font_family',
    'button_text',
    'thank_you_message',
])]
class SpaceBranding extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'space_branding';

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }
}
