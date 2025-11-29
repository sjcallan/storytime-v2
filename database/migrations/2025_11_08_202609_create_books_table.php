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
        Schema::create('books', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('title')->nullable();
            $table->string('status')->default('draft');
            $table->integer('age_level')->nullable();
            $table->string('genre')->nullable();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->text('plot')->nullable();
            $table->string('type')->nullable();
            $table->string('author')->nullable();
            $table->dateTime('last_opened_date')->nullable();
            $table->boolean('is_published')->default(false);
            $table->longText('user_characters')->nullable();
            $table->longText('scene')->nullable();
            $table->dateTime('published_at')->nullable();
            $table->text('additional_instructions')->nullable();
            $table->text('first_chapter_prompt')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};
