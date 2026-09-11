<?php

namespace App\Http\Requests;

use App\Models\Tag;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class CreateTagRequest extends FormRequest
{
    /**
     * No authentication/authorization exists in v1 (NFR3) — always allowed.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Trims `name` before validation runs, merging the trimmed value back
     * into the request — so `required`, the duplicate check, and the value
     * eventually persisted (via `validated('name')`) are all
     * trim-normalized. Without this, a whitespace-only string passes
     * `required` and untrimmed padding (e.g. "Finance ") defeats the
     * duplicate check against "Finance".
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
     * Case-insensitive duplicate detection (Boundaries & Constraints,
     * spec-3-5: "finance"/"Finance" must never coexist) — a closure rather
     * than `Rule::unique()`, whose case sensitivity depends on the
     * database's column collation and would silently diverge between
     * sqlite/mysql. The comparison itself happens entirely in PHP via
     * `Str::lower()` (multibyte-aware, e.g. "École"/"école") rather than
     * relying on SQL `LOWER()`, whose behavior differs by driver — SQLite's
     * built-in `LOWER()` only folds ASCII (no ICU), so comparing it against
     * `Str::lower()` could let an accented duplicate slip through on
     * SQLite. Fetching every existing name and folding it client-side keeps
     * the check identical on every driver.
     */
    private function notADuplicateName(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            if (! is_string($value)) {
                return;
            }

            $candidate = Str::lower($value);

            $exists = Tag::query()
                ->pluck('name')
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
