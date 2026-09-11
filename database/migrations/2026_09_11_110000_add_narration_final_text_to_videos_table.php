<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->longText('narration_final_text')->nullable()->after('narration');
        });

        DB::table('videos')->whereNull('narration_final_text')->update(['narration_final_text' => DB::raw('narration')]);
    }

    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropColumn('narration_final_text');
        });
    }
};
