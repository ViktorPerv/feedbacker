<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
