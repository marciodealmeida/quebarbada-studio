<?php

namespace Tests\Unit\Studio;

use App\Services\Studio\TtsService;
use RuntimeException;
use Tests\TestCase;

class TtsServiceTest extends TestCase
{
    public function test_narration_final_text_is_reconstructed_from_emotional_blocks(): void
    {
        $blocks = [
            ['label' => 'HOOK', 'text' => 'Ele disse que tinha uma bomba.', 'instructions' => 'Tenso.'],
            ['label' => 'FINAL', 'text' => 'Ele nunca foi identificado.', 'instructions' => 'Conclusivo.'],
        ];

        $narrationFinalText = app(TtsService::class)->narrationFinalText($blocks);

        $this->assertSame('Ele disse que tinha uma bomba. Ele nunca foi identificado.', $narrationFinalText);
    }

    public function test_tts_rejects_blocks_that_do_not_match_final_narration_text(): void
    {
        $blocks = [
            ['label' => 'HOOK', 'text' => 'Ele disse que tinha uma bomba.', 'instructions' => 'Tenso.'],
        ];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Os blocos de TTS devem recompor exatamente narration_final_text.');

        app(TtsService::class)->synthesizeEmotionalBlocks('Ele disse que tinha uma arma.', $blocks, 'studio/tests');
    }
}
