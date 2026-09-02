<?php

namespace App\Models;

use App\Enums\DocumentSource;
use App\Enums\ExtractionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Document extends Model
{
    use HasFactory;

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
}
