<?php

namespace App\Http\Requests;

use App\Models\Tag;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class RenameTagRequest extends FormRequest
{
    /**
     * No authentication/authorization exists in v1 (NFR3) — always allowed.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Mirrors CreateTagRequest::prepareForValidation() exactly — trims
     * `name` before validation so `required`, the duplicate check, and the
     * persisted value are all trim-normalized.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->name)) {
            $this->merge(['name' => trim($this->name)]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                $this->notADuplicateName(),
            ],
        ];
    }

    /**
     * Mirrors CreateTagRequest::notADuplicateName() exactly, excluding the
     * tag being renamed itself (Code Map, spec-3-5) — otherwise renaming a
     * tag to a case-variant of its own current name (e.g. "Finance" ->
     * "finance") would be wrongly rejected as a duplicate of itself. The
     * comparison happens entirely in PHP via `Str::lower()` (multibyte-aware)
     * rather than SQL `LOWER()`, whose ASCII-only folding on SQLite could
     * miss an accented duplicate that `Str::lower()` would catch.
     */
    private function notADuplicateName(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            if (! is_string($value)) {
                return;
            }

            $tag = $this->route('tag');
            $candidate = Str::lower($value);

            $exists = Tag::query()
                ->pluck('name', 'id')
                ->when($tag instanceof Tag, fn ($names) => $names->forget($tag->id))
                ->contains(fn (string $name) => Str::lower($name) === $candidate);

            if ($exists) {
                $fail("Un tag « {$value} » existe déjà.");
            }
        };
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Merci de saisir un nom de tag.',
            'name.max' => 'Le nom du tag est trop long (255 caractères maximum).',
        ];
    }
}
