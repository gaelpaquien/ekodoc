<?php

namespace App\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Flat categorization (AD-5): no hierarchy, no `parent_id`. `(id, name)`
 * only — no Eloquent timestamps, matching the migration's literal shape.
 * `category_id` is written exclusively through CategorizeDocumentAction
 * (AD-16); a Category row itself is only ever created via
 * CreateCategoryAction, which enforces case-insensitive name uniqueness.
 */
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'name',
    ];

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }
}
