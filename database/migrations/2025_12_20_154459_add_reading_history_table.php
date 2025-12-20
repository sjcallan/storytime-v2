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
        Schema::create('reading_history', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('book_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('profile_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('chapter_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('last_read_at');
            $table->integer('current_chapter_number')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reading_history');
    }
};
