<?php

use App\Enums\ExtractionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            // Existing rows predate async extraction and already went through
            // the old synchronous attempt to completion (success or silent
            // failure) — 'completed' is the accurate historical default.
            $table->enum('extraction_status', array_column(ExtractionStatus::cases(), 'value'))
                ->default(ExtractionStatus::Completed->value)
                ->after('mime_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn('extraction_status');
        });
    }
};
