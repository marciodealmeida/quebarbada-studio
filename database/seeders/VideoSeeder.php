<?php

namespace Database\Seeders;

use App\Models\Video;
use App\Models\VideoScene;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Database\Seeder;

class VideoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $video = Video::factory()->create();

        VideoScene::factory()
            ->count(12)
            ->for($video)
            ->sequence(fn (Sequence $sequence): array => ['position' => $sequence->index + 1])
            ->create();
    }
}
