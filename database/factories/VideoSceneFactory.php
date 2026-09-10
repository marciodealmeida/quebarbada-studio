<?php

namespace Database\Factories;

use App\Models\Video;
use App\Models\VideoScene;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VideoScene>
 */
class VideoSceneFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'video_id' => Video::factory(), 'position' => 1, 'duration_seconds' => 6,
            'narration' => fake()->sentence(), 'visual_prompt' => fake()->sentence(),
            'clip_search_terms' => [fake()->words(3, true)], 'asset_metadata' => [],
        ];
    }
}
