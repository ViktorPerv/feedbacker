<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\WidgetLayoutEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Валидация создания и редактирования виджета.
 *
 * Конфигурация содержит небольшой фиксированный набор параметров, поэтому в БД
 * не могут попасть значения, которых не ожидает средство отображения.
 */
class WidgetRequest extends FormRequest
{
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
            'name' => ['required', 'string', 'max:255'],
            'layout' => ['required', Rule::in(WidgetLayoutEnum::values())],
            'is_active' => ['boolean'],

            'config' => ['array'],
            'config.theme' => ['required', Rule::in(['light', 'dark'])],
            'config.columns' => ['required', 'integer', 'between:1,6'],
            'config.limit' => ['required', 'integer', 'between:1,200'],
            'config.accent' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'config.heading' => ['nullable', 'string', 'max:120'],
            'config.ratings' => ['boolean'],
            'config.footer' => ['boolean'],
            'config.featured_only' => ['boolean'],
            'config.min_rating' => ['nullable', 'integer', 'between:1,5'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'config.accent.regex' => 'The accent colour must be a hex value like #4F46E5.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $config = (array) $this->input('config', []);

        // Checkboxes arrive as "on"/absent; normalise so the boolean rule and
        // the stored JSON are predictable.
        foreach (['ratings', 'footer', 'featured_only'] as $flag) {
            $config[$flag] = filter_var($config[$flag] ?? false, FILTER_VALIDATE_BOOL);
        }

        foreach (['accent', 'heading', 'min_rating'] as $optional) {
            if (($config[$optional] ?? null) === '' || ($config[$optional] ?? null) === '0') {
                $config[$optional] = null;
            }
        }

        $this->merge([
            'config' => $config,
            'is_active' => filter_var($this->input('is_active', false), FILTER_VALIDATE_BOOL),
        ]);
    }

    /**
     * Возвращает конфигурацию для сохранения, дополняя отсутствующие значения
     * настройками макета по умолчанию.
     *
     * @return array<string, mixed>
     */
    public function configData(): array
    {
        $layout = WidgetLayoutEnum::from($this->validated('layout'));

        return array_merge($layout->defaultConfig(), $this->validated('config'));
    }
}
