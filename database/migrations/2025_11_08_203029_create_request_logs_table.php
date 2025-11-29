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
        Schema::create('request_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->default('0')->constrained()->cascadeOnDelete();
            $table->foreignUlid('book_id')->default('0')->constrained()->cascadeOnDelete();
            $table->foreignUlid('chapter_id')->default('0')->constrained()->cascadeOnDelete();
            $table->string('item_type')->nullable();
            $table->longText('request')->nullable();
            $table->longText('response')->nullable();
            $table->integer('response_status_code')->nullable();
            $table->decimal('response_time', 22, 6);
            $table->string('open_ai_id')->nullable();
            $table->string('model')->nullable();
            $table->integer('prompt_tokens')->nullable();
            $table->integer('completion_tokens')->nullable();
            $table->integer('total_tokens')->nullable();
            $table->decimal('cost_per_token', 16, 8)->default(0);
            $table->decimal('total_cost', 16, 8)->default(0);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('request_logs');
    }
};
