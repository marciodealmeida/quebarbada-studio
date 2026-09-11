<?php

namespace Tests\Feature\Console\Commands;

use App\Models\Video;
use App\Services\Studio\TopicGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerateContentCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_persists_a_complete_video_plan_without_media_generation(): void
    {
        $this->mock(TopicGeneratorService::class, function ($mock): void {
            $mock->shouldReceive('generate')->once()->andReturn([
                'topic' => 'O sequestro do sinal Max Headroom: a invasão que ninguém assumiu',
                'content_type' => 'mistério da internet documentado',
                'editorial_note' => 'Tema de teste.',
                'editorial_score' => 76,
                'editorial_ranking' => [],
            ]);
        });

        $this->artisan('conteudo:gerar que-sinistro --dry-run')
            ->expectsOutputToContain('Dry-run concluído')
            ->assertSuccessful();

        $video = Video::query()->with('scenes')->sole();

        $this->assertSame('que-sinistro', $video->channel->slug);
        $this->assertSame('planned', $video->status);
        $this->assertSame('1080x1920', $video->resolution);
        $this->assertSame(72, $video->estimated_duration_seconds);
        $this->assertCount(8, $video->scenes);
        $this->assertSame('0.72', $video->estimated_cost_usd);
        $this->assertTrue($video->metadata['dry_run']);
        $this->assertSame('O sequestro do sinal Max Headroom: a invasão que ninguém assumiu', $video->topic);
        $this->assertSame($video->narration, $video->narration_final_text);
        $this->assertSame('clímax', $video->scenes->first()->scene_type);
        $this->assertSame('pexels', $video->scenes->first()->asset_provider);
        $this->assertSame('television', $video->scenes->first()->required_elements[0]);
        $this->assertSame($video->scenes->first()->factual_reference, $video->scenes->first()->narrated_event);
        $this->assertSame(['television', 'static'], $video->scenes->first()->required_event_visuals);
        $this->assertNull($video->scenes->first()->event_coverage_score);
    }
}
