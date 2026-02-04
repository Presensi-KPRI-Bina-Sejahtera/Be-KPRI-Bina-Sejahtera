<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['presence_location_id']);
        });

        // Make nullable and remove default.
        DB::statement('ALTER TABLE users MODIFY presence_location_id BIGINT UNSIGNED NULL');

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('presence_location_id')
                ->references('id')
                ->on('presence_locations')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['presence_location_id']);
        });

        // Best-effort rollback: fill nulls with 1 (assumes presence_locations.id=1 exists).
        DB::statement('UPDATE users SET presence_location_id = 1 WHERE presence_location_id IS NULL');

        DB::statement('ALTER TABLE users MODIFY presence_location_id BIGINT UNSIGNED NOT NULL DEFAULT 1');

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('presence_location_id')
                ->references('id')
                ->on('presence_locations')
                ->restrictOnDelete();
        });
    }
};
