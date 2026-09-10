<?php

use App\Enums\ExtractionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A document's independent source files (spec-3-3, FR13) — distinct
     * from `documents.file_path`, which holds the single original file of
     * an *imported* document. `document_id` cascades on delete for the row
     * only (Boundaries & Constraints: "jamais cascadeOnDelete() nu pour les
     * fichiers") — the file itself is always removed explicitly first, by
     * DeleteDocumentAction/DetachDocumentFileAction, never left to the
     * database to clean up on its own.
     *
     * `file_path`/`mime_type`/`original_filename` are non-nullable: unlike
     * a Document (which may be `source = created` with no file at all), an
     * attachment only ever exists once its file is actually stored.
     * `extraction_status` mirrors `documents.extraction_status` exactly —
     * same enum, same default — so ExtractDocumentTextJob can treat a
     * Document and a DocumentAttachment the same way (Code Map).
     */
    public function up(): void
    {
        Schema::create('document_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->string('file_path');
            $table->string('mime_type');
            $table->string('original_filename');
            $table->longText('extracted_text')->nullable();
            $table->enum('extraction_status', array_column(ExtractionStatus::cases(), 'value'))
                ->default(ExtractionStatus::Pending->value);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_attachments');
    }
};
