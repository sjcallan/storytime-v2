<?php

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
        Schema::table('books', function (Blueprint $table) {
            $table->foreignUlid('cover_image_id')->nullable()->after('cover_image_status')->constrained('images')->nullOnDelete();
        });

        Schema::table('chapters', function (Blueprint $table) {
            $table->foreignUlid('header_image_id')->nullable()->after('image')->constrained('images')->nullOnDelete();
        });

        Schema::table('characters', function (Blueprint $table) {
            $table->foreignUlid('portrait_image_id')->nullable()->after('portrait_image_prompt')->constrained('images')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cover_image_id');
        });

        Schema::table('chapters', function (Blueprint $table) {
            $table->dropConstrainedForeignId('header_image_id');
        });

        Schema::table('characters', function (Blueprint $table) {
            $table->dropConstrainedForeignId('portrait_image_id');
        });
    }
};
