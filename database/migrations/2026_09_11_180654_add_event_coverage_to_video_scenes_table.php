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
        Schema::table('video_scenes', function (Blueprint $table) {
            $table->text('narrated_event')->nullable()->after('factual_reference');
            $table->json('required_event_visuals')->nullable()->after('required_elements');
            $table->unsignedTinyInteger('event_coverage_score')->nullable()->after('importance');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('video_scenes', function (Blueprint $table) {
            $table->dropColumn(['narrated_event', 'required_event_visuals', 'event_coverage_score']);
        });
    }
};
