<?php

namespace App\Models;

use App\Enums\ExtractionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A file attached to a document independently of its `content_html`
 * (spec-3-3, FR13) — the source PDF/Word/Excel behind a document authored
 * in the editor, say, rather than merged into its text. Written exclusively
 * through AttachDocumentFileAction/DetachDocumentFileAction (immediate) or
 * CreateDocumentAction::relocateDraftAttachments() (a draft's first save) —
 * never `sync()`/`attach()` directly, mirroring how `document_tag` is
 * written only through SyncDocumentTagsAction.
 */
class DocumentAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id',
        'file_path',
        'original_filename',
        'mime_type',
        'extracted_text',
        'extraction_status',
    ];

    protected function casts(): array
    {
        return [
            'extraction_status' => ExtractionStatus::class,
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
