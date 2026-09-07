<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Holds the WYSIWYG-authored body of a created document (`source =
     * created`) — nullable because imported documents never populate it,
     * mirroring how `file_path`/`mime_type` stay null for created ones
     * (spec-2-1).
     */
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->longText('content_html')->nullable()->after('mime_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn('content_html');
        });
    }
};
