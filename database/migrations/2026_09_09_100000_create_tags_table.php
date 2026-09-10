<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Flat, unlimited tagging (sprint-change-proposal 2026-09-09, AD-5
     * amendée): `(id, name)` only — no hierarchy, no `parent_id`, mirroring
     * the categories table it replaces. Tag creation/rename/deletion is out
     * of scope for this story (page Configuration = story 3.5) — the table
     * is populated via `TagFactory`/tinker in the meantime.
     */
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tags');
    }
};
