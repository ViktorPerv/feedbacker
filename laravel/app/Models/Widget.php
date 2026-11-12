<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WidgetLayoutEnum;
use Database\Factories\WidgetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Настраиваемый виджет отзывов.
 *
 * @property string $id
 * @property string $space_id
 * @property string $name
 * @property WidgetLayoutEnum $layout
 * @property string $token
 * @property array<string, mixed> $config
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Space $space
 */
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
     * Использует публичный токен вместо UUID для привязки модели маршрута,
     * поскольку значение попадает в исходный код сторонних страниц.
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
     * Объединяет сохранённую конфигурацию со значениями макета по умолчанию,
     * чтобы старые настройки продолжали корректно отображаться.
     *
     * @return array<string, mixed>
     */
    public function configWithDefaults(): array
    {
        return array_merge($this->layout->defaultConfig(), $this->config ?? []);
    }

    /**
     * Возвращает адрес встраиваемого скрипта.
     *
     * Адрес ведёт к сервису виджетов, а не к приложению: путь
     * `/w/{slug}/{token}.js` обслуживает Go-сервер. Функция url() создала бы
     * ссылку на отсутствующий маршрут Laravel.
     */
    public function embedUrl(): string
    {
        $base = rtrim((string) config('widget.public_url'), '/');

        return "{$base}/w/{$this->space->slug}/{$this->token}.js";
    }

    /**
     * Возвращает фрагмент кода для вставки на сайт пользователя.
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
