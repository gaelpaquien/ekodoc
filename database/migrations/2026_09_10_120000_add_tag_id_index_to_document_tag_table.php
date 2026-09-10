<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The existing unique index on `document_tag` is `(document_id, tag_id)`
     * — a composite index whose leftmost column is `document_id`, so it does
     * not serve a lookup by `tag_id` alone (Code review, spec-3-1). Added as
     * its own migration rather than editing the already-applied
     * 2026_09_09_100100 migration, so existing local data never needs a
     * `migrate:fresh` to pick it up.
     */
    public function up(): void
    {
        Schema::table('document_tag', function (Blueprint $table) {
            $table->index('tag_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_tag', function (Blueprint $table) {
            $table->dropIndex(['tag_id']);
        });
    }
};
