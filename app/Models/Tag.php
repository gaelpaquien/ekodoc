<?php

namespace App\Models;

use Database\Factories\TagFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Flat tag (sprint-change-proposal 2026-09-09, AD-5 amendée): no hierarchy,
 * `(id, name)` only — no Eloquent timestamps, matching the migration's
 * literal shape. The `document_tag` pivot is written exclusively through
 * SyncDocumentTagsAction, always via a full `sync()` (Boundaries &
 * Constraints, spec-3-1). Creating/renaming/deleting a Tag itself is out of
 * scope for this story (story 3.5) — rows come from TagFactory/tinker in
 * the meantime.
 */
class Tag extends Model
{
    /** @use HasFactory<TagFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'name',
    ];

    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(Document::class);
    }
}
