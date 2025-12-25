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
        Schema::table('request_logs', function (Blueprint $table) {
            $table->string('type')->default('text')->after('item_type');
            $table->integer('input_images_count')->nullable()->after('total_tokens');
            $table->integer('output_images_count')->nullable()->after('input_images_count');
            $table->decimal('cost_per_input_image', 16, 8)->nullable()->after('output_images_count');
            $table->decimal('cost_per_output_image', 16, 8)->nullable()->after('cost_per_input_image');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('request_logs', function (Blueprint $table) {
            $table->dropColumn([
                'type',
                'input_images_count',
                'output_images_count',
                'cost_per_input_image',
                'cost_per_output_image',
            ]);
        });
    }
};
