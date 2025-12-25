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
        Schema::create('moderations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignUlid('profile_id')->nullable()->constrained()->cascadeOnDelete();
            $table->longText('input');
            $table->json('response');
            $table->boolean('flagged')->default(false);
            $table->json('categories')->nullable();
            $table->json('category_scores')->nullable();
            $table->string('model')->nullable();
            $table->string('moderation_id')->nullable();
            $table->string('source')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['user_id', 'flagged']);
            $table->index('flagged');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('moderations');
    }
};
