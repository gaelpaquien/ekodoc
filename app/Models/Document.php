<?php

namespace App\Models;

use App\Enums\DocumentSource;
use App\Enums\ExtractionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
     * Scout index limited to `extracted_text` (Boundaries & Constraints,
     * spec-1-6) — title/metadata deliberately excluded, driver `database`
     * runs this straight through a `LIKE`/fulltext query, no separate index
     * to keep in sync.
     */
    public function toSearchableArray(): array
    {
        return [
            'extracted_text' => $this->extracted_text,
        ];
    }
}
