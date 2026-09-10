<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Explicit retirement of the single-category mechanism (sprint-change-
     * proposal 2026-09-09, AD-5 amendée) — replaced by tags/document_tag
     * above. Deliberately no data migration of existing `category_id`
     * values into `document_tag` rows (Boundaries & Constraints, spec-3-1):
     * an existing document simply ends up with zero tags after this runs.
     * The foreign key on `documents.category_id` is dropped before the
     * column itself, and before the `categories` table, so the drop order
     * never violates the constraint it depends on.
     */
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropColumn('category_id');
        });

        Schema::dropIfExists('categories');
    }

    /**
     * Reverse the migrations.
     *
     * Recreates the table/column shape only — deliberately does not restore
     * any data, mirroring up()'s own no-data-migration stance.
     */
    public function down(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->foreignId('category_id')
                ->nullable()
                ->after('extraction_status')
                ->constrained()
                ->nullOnDelete();
        });
    }
};
