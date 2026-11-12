<?php

namespace App\Models;

use App\Enums\WidgetLayoutEnum;
use Database\Factories\WidgetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['space_id', 'name', 'layout', 'token', 'config', 'is_active'])]
class Widget extends Model
{
    /** @use HasFactory<WidgetFactory> */
    use HasFactory, HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'layout' => WidgetLayoutEnum::class,
            'config' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Embeds address a widget by its public token, never by UUID: the value
     * ends up in third-party page source.
     */
    public function getRouteKeyName(): string
    {
        return 'token';
    }

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    /**
     * The saved configuration merged over the layout's defaults, so a config
     * written before a new option existed still renders.
     *
     * @return array<string, mixed>
     */
    public function configWithDefaults(): array
    {
        return array_merge($this->layout->defaultConfig(), $this->config ?? []);
    }

    /**
     * The public script URL for this widget.
     */
    /**
     * Where the embeddable script lives.
     *
     * Points at the widget service, not at the application: the Go server is
     * what answers `/w/{slug}/{token}.js`. Using url() here would generate a
     * link to a route Laravel does not have.
     */
    public function embedUrl(): string
    {
        $base = rtrim((string) config('widget.public_url'), '/');

        return "{$base}/w/{$this->space->slug}/{$this->token}.js";
    }

    /**
     * The snippet a user pastes into their site.
     */
    public function embedSnippet(): string
    {
        return sprintf(
            "<div data-feedbacker=\"%s\"></div>\n<script async src=\"%s\"></script>",
            $this->token,
            $this->embedUrl(),
        );
    }

    protected static function booted(): void
    {
        static::creating(function (Widget $widget): void {
            $widget->token ??= Str::random(24);
        });
    }
}
