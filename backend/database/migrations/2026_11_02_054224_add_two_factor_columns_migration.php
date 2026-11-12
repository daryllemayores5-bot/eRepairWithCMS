<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // The shared identity migration already owns these columns. Keep this
        // generated migration as a no-op so existing migration histories remain valid.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // The owning shared identity migration removes its own columns on rollback.
    }
};
