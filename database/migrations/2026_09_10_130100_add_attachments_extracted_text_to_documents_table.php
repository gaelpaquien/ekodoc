<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A real, queried-directly column — not a value computed in memory at
     * search time — because Scout's `database` driver runs a `WHERE
     * <column> LIKE` straight against the columns named by
     * `toSearchableArray()` (`vendor/laravel/scout/src/Engines/DatabaseEngine.php:280-316`),
     * never re-executing that method to aggregate related rows on the fly.
     * Kept up to date by `Document::syncAttachmentsExtractedText()`
     * whenever an attachment is attached, detached, or finishes extraction
     * (Boundaries & Constraints, spec-3-3).
     */
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->longText('attachments_extracted_text')->nullable()->after('extracted_text');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn('attachments_extracted_text');
        });
    }
};
