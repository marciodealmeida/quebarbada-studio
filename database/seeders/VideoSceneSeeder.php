<?php

namespace Database\Seeders;

use App\Models\Video;
use App\Models\VideoScene;
use Illuminate\Database\Seeder;

class VideoSceneSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        VideoScene::factory()->for(Video::factory())->create();
    }
}
