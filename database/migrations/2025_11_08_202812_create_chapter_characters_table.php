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
        Schema::create('chapter_character', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('book_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('chapter_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('character_id')->constrained()->cascadeOnDelete();
            $table->text('inner_thoughts')->nullable();
            $table->text('experience')->nullable();
            $table->text('goals')->nullable();
            $table->text('personal_motivations')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chapter_character');
    }
};
