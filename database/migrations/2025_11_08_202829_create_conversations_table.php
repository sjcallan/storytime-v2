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
        Schema::create('conversations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('character_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type')->nullable();
            $table->string('character_name')->nullable();
            $table->string('character_age')->nullable();
            $table->string('character_gender')->nullable();
            $table->string('character_nationality')->nullable();
            $table->text('character_description')->nullable();
            $table->text('character_backstory')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
