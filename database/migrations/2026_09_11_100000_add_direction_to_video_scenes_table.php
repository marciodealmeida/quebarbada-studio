<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('video_scenes', function (Blueprint $table) {
            $table->string('scene_type')->default('ambientação')->after('narration');
            $table->string('sound_effect')->nullable()->after('clip_search_terms');
            $table->string('asset_provider')->default('pexels')->after('clip_search_terms');
            $table->string('motion_design')->default('slow_zoom')->after('sound_effect');
        });
    }

    public function down(): void
    {
        Schema::table('video_scenes', function (Blueprint $table) {
            $table->dropColumn(['scene_type', 'sound_effect', 'motion_design']);
        });
    }
};
