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
        Schema::create('images', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('book_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('chapter_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('character_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('profile_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type'); // book_cover, character_portrait, chapter_header, chapter_inline
            $table->text('image_url')->nullable();
            $table->text('prompt')->nullable();
            $table->text('error')->nullable();
            $table->string('status')->default('pending'); // pending, processing, complete, error, cancelled
            $table->unsignedInteger('paragraph_index')->nullable(); // for chapter_inline type
            $table->string('aspect_ratio')->default('16:9');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['book_id', 'type']);
            $table->index(['chapter_id', 'type']);
            $table->index(['character_id', 'type']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('images');
    }
};
