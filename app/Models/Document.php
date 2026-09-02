<?php

namespace App\Models;

use App\Enums\DocumentSource;
use App\Enums\ExtractionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
     * `category_id` is deliberately absent from $fillable: the only code
     * path allowed to write it is CategorizeDocumentAction (AD-16), which
     * uses forceFill() — mirroring how ImportDocumentAction itself sets
     * `file_path` as a second, deliberate write after creation.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
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
