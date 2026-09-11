<?php

namespace Tests\Unit\Studio;

use App\Services\Studio\ScriptGeneratorService;
use App\Services\Studio\VideoRendererService;
use Tests\TestCase;

class VideoRendererServiceTest extends TestCase
{
    public function test_subtitle_blocks_are_derived_exactly_from_final_narration_text(): void
    {
        $narrationFinalText = 'Nove pessoas cortaram a própria barraca. Depois, fugiram para a neve.';
        $subtitles = app(VideoRendererService::class)->subtitles($narrationFinalText, 12.0);
        $dialogues = array_filter(explode("\n", $subtitles), fn (string $line): bool => str_starts_with($line, 'Dialogue:'));
        $subtitleText = collect($dialogues)
            ->map(fn (string $line): string => str_replace('\\,', ',', explode(',', $line, 10)[9]))
            ->implode(' ');

        $this->assertSame($narrationFinalText, $subtitleText);
    }

    public function test_hook_requires_tension_and_rejects_historical_context(): void
    {
        $scriptGenerator = app(ScriptGeneratorService::class);

        $this->assertTrue($scriptGenerator->hasRetentiveHook('Nove pessoas cortaram a própria barraca e fugiram para a neve.'));
        $this->assertFalse($scriptGenerator->hasRetentiveHook('Em 1977, um radiotelescópio registrou um sinal estranho.'));
    }
}
