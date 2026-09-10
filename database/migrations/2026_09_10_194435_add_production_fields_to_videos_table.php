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
        Schema::table('videos', function (Blueprint $table) {
            $table->string('output_path')->nullable()->after('metadata');
            $table->unsignedSmallInteger('actual_duration_seconds')->nullable()->after('output_path');
            $table->decimal('actual_cost_usd', 8, 2)->nullable()->after('actual_duration_seconds');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropColumn(['output_path', 'actual_duration_seconds', 'actual_cost_usd']);
        });
    }
};
