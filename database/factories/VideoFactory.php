<?php

namespace Database\Factories;

use App\Models\Channel;
use App\Models\Video;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Video>
 */
class VideoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'channel_id' => Channel::factory(),
            'status' => 'planned', 'content_type' => 'lenda urbana', 'topic' => fake()->sentence(),
            'script' => fake()->paragraphs(3, true), 'narration' => fake()->paragraphs(3, true),
            'resolution' => '1080x1920', 'estimated_duration_seconds' => 72,
            'estimated_cost_usd' => 0, 'metadata' => [],
        ];
    }
}
