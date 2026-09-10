<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncDocumentTagsRequest extends FormRequest
{
    /**
     * No authentication/authorization exists in v1 (NFR3) — always allowed.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The Document Detail page's TagSelector never sends `null` in
     * practice, only an array (possibly empty) — normalized defensively
     * anyway (Code review, spec-3-1) so an explicit `null` from any other
     * caller is treated the same as "no tags" rather than failing the
     * `array` rule.
     */
    protected function prepareForValidation(): void
    {
        if ($this->tag_ids === null) {
            $this->merge(['tag_ids' => []]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tag_ids' => ['array'],
            'tag_ids.*' => ['distinct', 'integer', Rule::exists('tags', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tag_ids.array' => 'Tags invalides.',
            'tag_ids.*.integer' => 'Tag invalide.',
            'tag_ids.*.exists' => 'Tag invalide.',
        ];
    }
}
