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
        Schema::create('chapters', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('title')->nullable();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('book_id')->constrained()->cascadeOnDelete();
            $table->text('body')->nullable();
            $table->text('summary')->nullable();
            $table->text('user_prompt')->nullable();
            $table->text('error')->nullable();
            $table->boolean('final_chapter')->default(false);
            $table->integer('sort');
            $table->text('cta')->nullable();
            $table->decimal('cta_total_cost', 16, 8)->default(0);
            $table->text('image_prompt')->nullable();
            $table->text('image')->nullable();
            $table->longText('book_summary')->nullable();
            $table->string('status')->default('draft');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chapters');
    }
};
