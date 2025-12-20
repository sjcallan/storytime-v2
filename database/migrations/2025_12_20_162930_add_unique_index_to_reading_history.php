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
        Schema::table('reading_history', function (Blueprint $table) {
            $table->unique(['user_id', 'book_id', 'profile_id'], 'reading_history_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reading_history', function (Blueprint $table) {
            $table->dropUnique('reading_history_unique');
        });
    }
};
