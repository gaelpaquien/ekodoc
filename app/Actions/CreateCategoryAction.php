<?php

namespace App\Actions;

use App\DataTransferObjects\CreateCategoryData;
use App\Models\Category;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Sole write point for creating a Category. Enforces name uniqueness
 * case-insensitively — regardless of the database's own collation — so
 * "Contrats" and "contrats" can never coexist as separate categories.
 */
class CreateCategoryAction
{
    public function __invoke(CreateCategoryData $data): Category
    {
        $name = trim($data->name);

        if ($this->nameAlreadyExists($name)) {
            throw $this->duplicateNameException($name);
        }

        try {
            return Category::create(['name' => $name]);
        } catch (QueryException $exception) {
            // The exists() check above closes most of the race window, but
            // two concurrent requests for the exact same name can still
            // both pass it before either commits — the column's own unique
            // constraint (migration) is the final backstop. Translate that
            // constraint violation into the same graceful validation error
            // instead of letting an uncaught QueryException surface.
            if ($this->isUniqueConstraintViolation($exception)) {
                throw $this->duplicateNameException($name);
            }

            throw $exception;
        }
    }

    private function nameAlreadyExists(string $name): bool
    {
        return Category::query()
            ->whereRaw('LOWER(name) = ?', [Str::lower($name)])
            ->exists();
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        // SQLSTATE 23000 (integrity constraint violation) covers unique
        // constraint violations consistently across MySQL, SQLite and
        // PostgreSQL.
        return $exception->getCode() === '23000';
    }

    private function duplicateNameException(string $name): ValidationException
    {
        return ValidationException::withMessages([
            'name' => "Une catégorie « {$name} » existe déjà.",
        ]);
    }
}
