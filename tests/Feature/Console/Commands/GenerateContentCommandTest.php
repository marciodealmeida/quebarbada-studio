<?php

namespace Tests\Feature\Console\Commands;

use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerateContentCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_persists_a_complete_video_plan_without_media_generation(): void
    {
        $this->artisan('conteudo:gerar que-sinistro --dry-run')
            ->expectsOutputToContain('Dry-run concluído')
            ->assertSuccessful();

        $video = Video::query()->with('scenes')->sole();

        $this->assertSame('que-sinistro', $video->channel->slug);
        $this->assertSame('planned', $video->status);
        $this->assertSame('1080x1920', $video->resolution);
        $this->assertSame(80, $video->estimated_duration_seconds);
        $this->assertCount(20, $video->scenes);
        $this->assertSame('0.92', $video->estimated_cost_usd);
        $this->assertTrue($video->metadata['dry_run']);
        $this->assertStringNotContainsString('Loira do Banheiro', $video->topic);
        $this->assertStringContainsString('não é prova de que algo sobrenatural tenha acontecido', $video->narration);
    }
}
