<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hosted_file_states', function (Blueprint $table) {
            $table->unsignedSmallInteger('id')->primary();
            $table->uuid('epoch');
            $table->unsignedBigInteger('revision')->default(0);
            $table->timestampsTz();
        });
        Schema::create('hosted_files', function (Blueprint $table) {
            $table->id();
            $table->string('root', 32);
            $table->text('path');
            $table->char('path_hash', 64);
            $table->boolean('is_directory');
            $table->unsignedBigInteger('size')->default(0);
            $table->char('checksum', 64)->nullable();
            // Base64 is an encoding, not encryption. Private files remain private.
            $table->longText('payload')->nullable();
            $table->timestampsTz();
            $table->unique(['root', 'path_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hosted_files');
        Schema::dropIfExists('hosted_file_states');
    }
};
