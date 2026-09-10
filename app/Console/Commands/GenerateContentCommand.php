<?php

namespace App\Console\Commands;

use App\Models\Channel;
use App\Models\Video;
use App\Services\Studio\AssetService;
use App\Services\Studio\ScenePlannerService;
use App\Services\Studio\ScriptGeneratorService;
use App\Services\Studio\TopicGeneratorService;
use App\Services\Studio\TtsService;
use App\Services\Studio\VideoRendererService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('conteudo:gerar {channel? : Slug do canal} {--dry-run : Planeja e persiste sem gerar mídia}')]
#[Description('Gera um plano de conteúdo vertical para um canal')]
class GenerateContentCommand extends Command
{
    public function handle(
        TopicGeneratorService $topicGenerator,
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
        $script = $scriptGenerator->generate($topic, $dryRun);
        $scenes = $scenePlanner->plan($script['narration'], $topic['topic']);
        $duration = array_sum(array_column($scenes, 'duration_seconds'));

        if ($duration < config('studio.video.min_seconds') || $duration > config('studio.video.max_seconds')) {
            $this->error('O planejamento ficou fora da duração permitida.');

            return self::FAILURE;
        }

        $estimatedCost = array_sum(config('studio.estimated_costs'));
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
            'narration' => $script['narration'],
            'resolution' => config('studio.video.resolution'),
            'estimated_duration_seconds' => $duration,
            'estimated_cost_usd' => $estimatedCost,
            'metadata' => [
                'editorial_note' => $topic['editorial_note'],
                'dry_run' => $dryRun,
                'services' => ['tts' => $tts->configuration(), 'assets' => $assets->configuration(), 'renderer' => $renderer->configuration()],
            ],
        ]);
        $video->scenes()->createMany($scenes);

        if (! $dryRun) {
            $directory = "studio/videos/{$video->id}";
            $audio = $tts->synthesize($video->narration, $directory);
            $actualDuration = $renderer->duration(\Storage::disk('local')->path($audio['path']));
            if ($actualDuration < config('studio.video.min_seconds') || $actualDuration > config('studio.video.max_seconds')) {
                $video->update(['status' => 'rejected_duration']);
                $this->error("A narração tem {$actualDuration}s e foi bloqueada antes de baixar assets.");

                return self::FAILURE;
            }
            $assets->downloadPexels($video->scenes()->get(), $directory);
            $rendered = $renderer->render($video, \Storage::disk('local')->path($audio['path']), $directory);
            $video->update(['status' => 'rendered', 'output_path' => $rendered['path'], 'actual_duration_seconds' => (int) round($rendered['duration']), 'actual_cost_usd' => $estimatedCost, 'metadata' => [...$video->metadata, 'tts' => $audio, 'render' => $rendered]]);
            $this->info('MP4 final: '.\Storage::disk('local')->path($rendered['path']));
        }

        $this->info("Vídeo {$video->id} planejado para {$channel->name}.");
        $this->line("{$duration}s, ".count($scenes).' cenas, custo estimado: US$ '.number_format($estimatedCost, 2));
        if ($dryRun) {
            $this->comment('Dry-run concluído: nenhuma imagem, clipe, áudio ou render foi gerado.');
        }

        return self::SUCCESS;
    }
}
