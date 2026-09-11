<?php

namespace App\Console\Commands;

use App\Models\Channel;
use App\Models\Video;
use App\Services\Studio\AssetService;
use App\Services\Studio\ResearchService;
use App\Services\Studio\ScenePlannerService;
use App\Services\Studio\ScriptGeneratorService;
use App\Services\Studio\TopicGeneratorService;
use App\Services\Studio\TtsService;
use App\Services\Studio\VideoRendererService;
use App\Services\Studio\VisualEvaluationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('conteudo:gerar {channel? : Slug do canal} {--dry-run : Planeja e persiste sem gerar mídia}')]
#[Description('Gera um plano de conteúdo vertical para um canal')]
class GenerateContentCommand extends Command
{
    public function handle(
        TopicGeneratorService $topicGenerator,
        ResearchService $researchService,
        VisualEvaluationService $visualEvaluation,
        ScriptGeneratorService $scriptGenerator,
        ScenePlannerService $scenePlanner,
        TtsService $tts,
        AssetService $assets,
        VideoRendererService $renderer,
    ): int {
        $channelSlug = (string) ($this->argument('channel') ?: config('studio.default_channel'));
        $dryRun = (bool) $this->option('dry-run');
        $channel = Channel::query()->firstOrCreate(
            ['slug' => $channelSlug],
            ['name' => str($channelSlug)->replace('-', ' ')->title()->toString(), 'settings' => []],
        );
        $topic = $topicGenerator->generate($channelSlug);
        $research = $researchService->research($topic);
        $visual = $visualEvaluation->evaluate($research);
        if ($visual['visual_score'] < 70) {
            $this->error('O tema foi rejeitado por potencial visual insuficiente.');

            return self::FAILURE;
        }
        $script = $scriptGenerator->generate($topic, $research, $dryRun);
        $narrationFinalText = $script['narration'];
        $scenes = $scenePlanner->plan($narrationFinalText, $topic['topic'], $topic['content_type'], $research);
        $duration = array_sum(array_column($scenes, 'duration_seconds'));

        if ($duration < config('studio.video.min_seconds') || $duration > config('studio.video.max_seconds')) {
            $this->error('O planejamento ficou fora da duração permitida.');

            return self::FAILURE;
        }

        $imageCount = count(array_filter($scenes, fn (array $scene): bool => $scene['asset_provider'] === 'openai-image'));
        $estimatedCost = array_sum(config('studio.estimated_costs')) + max(0, $imageCount - 1) * config('studio.estimated_costs.image_usd');
        if ($estimatedCost > config('studio.max_cost_usd')) {
            $this->error('O custo estimado excede o teto configurado.');

            return self::FAILURE;
        }

        $video = Video::query()->create([
            'channel_id' => $channel->id,
            'status' => $dryRun ? 'planned' : 'ready_for_production',
            'content_type' => $topic['content_type'],
            'topic' => $topic['topic'],
            'script' => $script['script'],
            'narration' => $narrationFinalText,
            'narration_final_text' => $narrationFinalText,
            'resolution' => config('studio.video.resolution'),
            'estimated_duration_seconds' => $duration,
            'estimated_cost_usd' => $estimatedCost,
            'metadata' => [
                'editorial_note' => $topic['editorial_note'],
                'editorial_score' => $topic['editorial_score'],
                'editorial_ranking' => $topic['editorial_ranking'],
                'research' => $research,
                'visual_score' => $visual['visual_score'],
                'visual_score_reasons' => $visual['reasons'],
                'audio_licensing' => 'Trilha e efeitos são sintetizados localmente por FFmpeg; não há asset de áudio de terceiros.',
                'dry_run' => $dryRun,
                'services' => ['tts' => $tts->configuration(), 'assets' => $assets->configuration(), 'renderer' => $renderer->configuration()],
            ],
        ]);
        $video->scenes()->createMany($scenes);

        if (! $dryRun) {
            $directory = "studio/videos/{$video->id}";
            $audio = $tts->synthesize($video->narration_final_text, $directory);
            $actualDuration = $renderer->duration(\Storage::disk('local')->path($audio['path']));
            $finalDuration = $actualDuration + config('studio.video.tail_seconds');
            if ($finalDuration < config('studio.video.min_seconds') || $finalDuration > config('studio.video.max_seconds')) {
                $video->update(['status' => 'rejected_duration']);
                $this->error("A duração final prevista é {$finalDuration}s e foi bloqueada antes de baixar assets.");

                return self::FAILURE;
            }
            $assets->acquire($video->scenes()->get(), $directory);
            $rendered = $renderer->render($video, \Storage::disk('local')->path($audio['path']), $directory);
            $video->update(['status' => 'rendered', 'output_path' => $rendered['path'], 'actual_duration_seconds' => (int) round($rendered['duration']), 'actual_cost_usd' => $estimatedCost, 'metadata' => [...$video->metadata, 'tts' => $audio, 'render' => $rendered]]);
            $this->info('MP4 final: '.\Storage::disk('local')->path($rendered['path']));
        }

        $this->info("Vídeo {$video->id} planejado para {$channel->name}.");
        $this->line("{$duration}s, ".count($scenes).' cenas, custo estimado: US$ '.number_format($estimatedCost, 2));
        if ($dryRun) {
            $this->line("Relatório editorial: {$topic['editorial_score']}/100; visual: {$visual['visual_score']}/100; ".count($research['key_facts']).' fatos; '.count($scenes)." cenas; {$imageCount} imagem(ns) IA; custo US$ ".number_format($estimatedCost, 2).'.');
            foreach ($scenes as $scene) {
                $this->line("Cena {$scene['position']}: {$scene['scene_type']} | {$scene['factual_reference']} | {$scene['asset_provider']} | ".implode(', ', $scene['clip_search_terms']));
            }
            $this->comment('Dry-run concluído: nenhuma imagem, clipe, áudio ou render foi gerado.');
        }

        return self::SUCCESS;
    }
}
