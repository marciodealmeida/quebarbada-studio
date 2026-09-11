<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('video_scenes', function (Blueprint $table) {
            $table->text('factual_reference')->nullable()->after('scene_type');
            $table->text('visual_intent')->nullable()->after('factual_reference');
            $table->json('fallback_search_terms')->nullable()->after('clip_search_terms');
            $table->json('required_elements')->nullable()->after('fallback_search_terms');
            $table->json('forbidden_elements')->nullable()->after('required_elements');
            $table->boolean('real_reference_candidate')->default(false)->after('asset_provider');
            $table->unsignedTinyInteger('importance')->default(3)->after('motion_design');
        });
    }

    public function down(): void
    {
        Schema::table('video_scenes', function (Blueprint $table) {
            $table->dropColumn(['factual_reference', 'visual_intent', 'fallback_search_terms', 'required_elements', 'forbidden_elements', 'real_reference_candidate', 'importance']);
        });
    }
};
