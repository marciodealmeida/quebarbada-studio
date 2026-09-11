<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('video_scenes', function (Blueprint $table) {
            $table->json('real_reference_candidates')->nullable()->after('real_reference_candidate');
            $table->string('real_reference_decision')->nullable()->after('real_reference_candidates');
            $table->text('real_reference_reason')->nullable()->after('real_reference_decision');
        });
    }

    public function down(): void
    {
        Schema::table('video_scenes', function (Blueprint $table) {
            $table->dropColumn(['real_reference_candidates', 'real_reference_decision', 'real_reference_reason']);
        });
    }
};
