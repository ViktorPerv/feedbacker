<?php

namespace App\Http\Requests;

use App\Models\Language;
use App\Models\Translation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TranslationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A translation's identity is its (locale, group, key) triple.
     *
     * That triple is only chosen when the entry is created. An edit rewrites
     * the wording and nothing else, so there is no identity left to check for
     * uniqueness — and no way for an edit to collide with another row.
     */
    private function isEdit(): bool
    {
        return $this->routeIs('translations.update');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        if ($this->isEdit()) {
            return [
                'value' => ['nullable', 'string', 'max:8000'],
            ];
        }

        return [
            'locale' => ['required', 'string', Rule::in(Language::codes())],
            'group' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9_*\-]+$/i'],
            'key' => [
                'required',
                'string',
                'max:191',
                'regex:/^[A-Za-z0-9_\-]+(\.[A-Za-z0-9_\-]+)*$/',
                // Two rows for one key in the same language and group would
                // make the catalogue ambiguous, so creation rejects it.
                Rule::unique('translations', 'key')
                    ->where('locale', $this->input('locale'))
                    ->where('group', $this->input('group')),
            ],
            'value' => ['nullable', 'string', 'max:8000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'key.regex' => 'Keys may contain letters, numbers, dashes, underscores and dots (for nesting).',
            'key.unique' => 'This key already exists for that language and group.',
            'group.regex' => 'Groups may contain letters, numbers, dashes and underscores.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'locale' => 'language',
            'value' => 'translation',
        ];
    }

    /**
     * The attributes for a new entry.
     *
     * @return array<string, mixed>
     */
    public function translationData(): array
    {
        $data = $this->validated();

        $data['key'] = trim($data['key'], '.');
        $data['value'] = $data['value'] ?? null;

        return $data;
    }
}
