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
        Schema::create('videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('planned')->index();
            $table->string('content_type');
            $table->string('topic');
            $table->longText('script');
            $table->longText('narration');
            $table->string('resolution')->default('1080x1920');
            $table->unsignedSmallInteger('estimated_duration_seconds');
            $table->decimal('estimated_cost_usd', 8, 2)->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('videos');
    }
};
