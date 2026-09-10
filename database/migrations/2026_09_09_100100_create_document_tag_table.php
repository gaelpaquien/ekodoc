<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Many-to-many pivot, no limit on tags per document (Boundaries &
     * Constraints, spec-3-1) — `cascadeOnDelete()` on both sides so deleting
     * either a document or a tag simply drops the matching pivot rows,
     * never leaving an orphaned reference. The unique pair prevents the
     * same tag from ever being attached twice to the same document, which
     * `sync()` (the pivot's sole write point, SyncDocumentTagsAction) would
     * otherwise silently allow duplicate calls to attempt.
     */
    public function up(): void
    {
        Schema::create('document_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->unique(['document_id', 'tag_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_tag');
    }
};
