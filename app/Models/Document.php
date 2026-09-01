<?php

namespace App\Models;

use App\Enums\DocumentSource;
use App\Enums\ExtractionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
}
