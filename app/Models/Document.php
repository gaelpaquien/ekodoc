<?php

namespace App\Models;

use App\Enums\DocumentSource;
use App\Enums\ExtractionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Scout\Searchable;

class Document extends Model
{
    use HasFactory;
    use Searchable;

    protected $fillable = [
        'title',
        'source',
        'file_path',
        'mime_type',
        'content_html',
        'extracted_text',
        'attachments_extracted_text',
        'extraction_status',
    ];

    protected function casts(): array
    {
        return [
            'source' => DocumentSource::class,
            'extraction_status' => ExtractionStatus::class,
        ];
    }

    /**
     * `document_tag` is written exclusively through SyncDocumentTagsAction,
     * always via a full `sync()` (Boundaries & Constraints, spec-3-1) —
     * never `attach()`/`detach()` incrementally, and never from any other
     * code path.
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)->orderBy('tags.name');
    }

    /**
     * A document's independently attached files (spec-3-3, FR13) — written
     * exclusively through AttachDocumentFileAction/DetachDocumentFileAction
     * or CreateDocumentAction::relocateDraftAttachments(), never `sync()`.
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(DocumentAttachment::class);
    }

    /**
     * Scout index limited to `extracted_text`/`attachments_extracted_text`
     * (Boundaries & Constraints, spec-1-6/spec-3-3) — title/metadata
     * deliberately excluded, driver `database` runs this straight through a
     * `LIKE`/fulltext query against the named columns, never re-executing
     * this method to aggregate related rows in memory (Design Notes,
     * spec-3-3) — hence `attachments_extracted_text` being a real column,
     * kept in sync by `syncAttachmentsExtractedText()` rather than derived
     * here from the `attachments` relation.
     */
    public function toSearchableArray(): array
    {
        return [
            'extracted_text' => $this->extracted_text,
            'attachments_extracted_text' => $this->attachments_extracted_text,
        ];
    }

    /**
     * Recomputes `attachments_extracted_text` from every current attachment
     * (Code Map, spec-3-3) — called after an attachment is attached,
     * detached, or finishes text extraction, so the aggregate a search ever
     * queries never drifts from the attachments actually present. Reloads
     * the relation fresh rather than trusting an already-loaded one, since
     * the caller may have just added/removed/updated a row out from under
     * it. `forceFill()->save()` mirrors ExtractDocumentTextJob's own writes
     * to `extracted_text` — never touches `content_html`/`isDirty` state on
     * the client (Boundaries & Constraints).
     */
    public function syncAttachmentsExtractedText(): void
    {
        // A fresh query, not the possibly-stale already-loaded `attachments`
        // relation — the caller may have just added/removed/updated a row
        // out from under it.
        $text = $this->attachments()
            ->pluck('extracted_text')
            ->filter()
            ->implode(' ');

        $this->forceFill([
            'attachments_extracted_text' => $text !== '' ? $text : null,
        ])->save();
    }
}
