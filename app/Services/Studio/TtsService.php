<?php

namespace App\Services\Studio;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class TtsService
{
    public function __construct(private HttpFactory $http) {}

    /** @return array{path: string, model: string, voice: string} */
    public function synthesize(string $narration, string $directory): array
    {
        $apiKey = config('studio.openai.api_key');
        if (blank($apiKey)) {
            throw new RuntimeException('OPENAI_API_KEY não está configurada.');
        }
        $model = config('studio.openai.tts_model');
        $voice = config('studio.openai.tts_voice');
        $audio = $this->http->withToken($apiKey)->connectTimeout(10)->timeout(180)->post('https://api.openai.com/v1/audio/speech', ['model' => $model, 'voice' => $voice, 'input' => $narration, 'instructions' => 'Narre em português brasileiro como quem conta um segredo real a uma pessoa próxima: íntimo, tenso e envolvente. A primeira frase deve ser curta e firme. Nos primeiros 15 segundos, mantenha ritmo rápido e entregue cada fato com clareza. Faça pausas dramáticas breves antes de revelações, dê ênfase discreta a números, nomes e à última frase. Aumente a tensão no clímax e termine com controle, sem soar como documentário, locutor de trailer ou personagem teatral.', 'speed' => 1.08, 'response_format' => 'mp3'])->throw()->body();
        $path = "{$directory}/narration.mp3";
        Storage::disk('local')->put($path, $audio);

        return compact('path', 'model', 'voice');
    }

    /**
     * @param  list<array{label: string, text: string, instructions: string}>  $blocks
     * @param  list<int>|null  $regenerateBlockIndexes
     * @return array{path: string, model: string, voice: string, blocks: list<array{label: string, duration: float, instructions: string}>}
     */
    public function synthesizeEmotionalBlocks(string $narrationFinalText, array $blocks, string $directory, ?array $regenerateBlockIndexes = null): array
    {
        if ($this->narrationFinalText($blocks) !== $this->normalizeText($narrationFinalText)) {
            throw new RuntimeException('Os blocos de TTS devem recompor exatamente narration_final_text.');
        }

        $apiKey = config('studio.openai.api_key');
        if (blank($apiKey)) {
            throw new RuntimeException('OPENAI_API_KEY não está configurada.');
        }

        $model = config('studio.openai.tts_model');
        $voice = config('studio.openai.tts_voice');
        $normalizedPaths = [];
        $blockMetadata = [];
        $regenerateBlockIndexes ??= array_keys($blocks);

        foreach ($blocks as $index => $block) {
            $sourcePath = Storage::disk('local')->path("{$directory}/tts-block-{$index}.mp3");
            $normalizedPath = Storage::disk('local')->path("{$directory}/tts-block-{$index}.wav");
            if (in_array($index, $regenerateBlockIndexes, true)) {
                $audio = $this->http->withToken($apiKey)->connectTimeout(10)->timeout(180)->post('https://api.openai.com/v1/audio/speech', [
                    'model' => $model,
                    'voice' => $voice,
                    'input' => $this->normalizeText($block['text']),
                    'instructions' => $block['instructions'],
                    'speed' => 1.08,
                    'response_format' => 'mp3',
                ])->throw()->body();
                Storage::disk('local')->put("{$directory}/tts-block-{$index}.mp3", $audio);
                Process::timeout(60)->run(['ffmpeg', '-y', '-i', $sourcePath, '-af', 'loudnorm=I=-16:TP=-1.5:LRA=7', '-ar', '48000', '-ac', '2', $normalizedPath])->throw();
            } elseif (! is_file($normalizedPath)) {
                throw new RuntimeException("O bloco de TTS {$index} não existe para reutilização.");
            }
            $normalizedPaths[] = $normalizedPath;
            $blockMetadata[] = [
                'label' => $block['label'],
                'duration' => $this->duration($normalizedPath),
                'instructions' => $block['instructions'],
            ];
        }

        $pausePath = Storage::disk('local')->path("{$directory}/tts-natural-pause.wav");
        Process::timeout(60)->run(['ffmpeg', '-y', '-f', 'lavfi', '-i', 'anullsrc=r=48000:cl=stereo', '-t', '0.20', $pausePath])->throw();
        $manifestPath = Storage::disk('local')->path("{$directory}/tts-concat.txt");
        $manifest = collect($normalizedPaths)
            ->flatMap(fn (string $path, int $index): array => $index === count($normalizedPaths) - 1 ? [$path] : [$path, $pausePath])
            ->map(fn (string $path): string => "file '".str_replace("'", "'\\''", $path)."'")
            ->implode("\n");
        Storage::disk('local')->put("{$directory}/tts-concat.txt", $manifest);
        $path = "{$directory}/narration.mp3";
        Process::timeout(180)->run(['ffmpeg', '-y', '-f', 'concat', '-safe', '0', '-i', $manifestPath, '-c:a', 'libmp3lame', '-b:a', '192k', Storage::disk('local')->path($path)])->throw();

        return ['path' => $path, 'model' => $model, 'voice' => $voice, 'blocks' => $blockMetadata];
    }

    /** @param list<array{label: string, text: string, instructions: string}> $blocks */
    public function narrationFinalText(array $blocks): string
    {
        $narrationFinalText = $this->normalizeText(implode(' ', array_map(fn (array $block): string => $block['text'] ?? '', $blocks)));
        if (blank($narrationFinalText)) {
            throw new RuntimeException('A narração final não pode ficar vazia.');
        }

        return $narrationFinalText;
    }

    /** @return array{provider: string, configured: bool} */
    public function configuration(): array
    {
        return ['provider' => 'openai', 'configured' => filled(config('studio.openai.api_key'))];
    }

    private function duration(string $path): float
    {
        $result = Process::timeout(30)->run(['ffprobe', '-v', 'error', '-show_entries', 'format=duration', '-of', 'default=noprint_wrappers=1:nokey=1', $path]);
        if ($result->failed()) {
            throw new RuntimeException($result->errorOutput());
        }

        return (float) trim($result->output());
    }

    private function normalizeText(string $text): string
    {
        return preg_replace('/\s+/u', ' ', trim($text)) ?: '';
    }
}
