<?php

namespace App\Services\Studio;

use App\Models\VideoScene;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class AssetService
{
    public function __construct(private HttpFactory $http) {}

    /** @param iterable<VideoScene> $scenes */
    public function downloadPexels(iterable $scenes, string $directory): void
    {
        $key = config('studio.pexels.api_key');
        if (blank($key)) {
            throw new RuntimeException('PEXELS_API_KEY não está configurada.');
        }
        $usedPexelsIds = VideoScene::query()
            ->whereNotNull('asset_metadata')
            ->get()
            ->map(fn (VideoScene $scene): ?int => data_get($scene->asset_metadata, 'pexels_id'))
            ->filter()
            ->all();
        foreach ($scenes as $scene) {
            if (in_array($scene->position, [1, 12, 15, 18], true)) {
                $this->generateImage($scene, $directory);

                continue;
            }
            $query = implode(' ', $scene->clip_search_terms);
            $videos = $this->http->withHeaders(['Authorization' => $key])->acceptJson()->connectTimeout(10)->timeout(60)->get('https://api.pexels.com/videos/search', ['query' => $query, 'orientation' => 'portrait', 'per_page' => 15])->throw()->json('videos', []);
            $candidate = collect($videos)->map(function (array $video): ?array {
                $file = collect($video['video_files'] ?? [])->filter(fn (array $file): bool => ($file['file_type'] ?? '') === 'video/mp4' && ($file['height'] ?? 0) >= 1280)->sortByDesc('height')->first();

                return $file ? ['id' => $video['id'], 'duration' => $video['duration'], 'file' => $file] : null;
            })->filter()->first(fn (array $video): bool => $video['duration'] >= 5 && ! in_array($video['id'], $usedPexelsIds, true));
            if (! $candidate) {
                throw new RuntimeException("Nenhum clipe Pexels vertical relevante foi encontrado para a cena {$scene->position}: {$query}");
            }
            $path = "{$directory}/scene-{$scene->position}.mp4";
            $this->http->connectTimeout(10)->timeout(180)->sink(Storage::disk('local')->path($path))->get($candidate['file']['link'])->throw();
            $scene->update(['asset_metadata' => ['provider' => 'pexels', 'pexels_id' => $candidate['id'], 'query' => $query, 'path' => $path, 'source_url' => $candidate['file']['link']]]);
            $usedPexelsIds[] = $candidate['id'];
        }
    }

    private function generateImage(VideoScene $scene, string $directory): void
    {
        $moments = [1 => 'immediate unsettling hook', 12 => 'first subtle manifestation', 15 => 'supernatural appearance', 18 => 'quiet disturbing conclusion'];
        $prompts = [
            1 => "Cinematic realistic Brazilian folklore scene, {$moments[1]}, inspired by this narration: {$scene->narration}. Low-key lighting, vertical 9:16, no gore, no text, no generic fantasy art.",
            12 => "Cinematic realistic Brazilian folklore scene, {$moments[12]}, inspired by this narration: {$scene->narration}. Low-key lighting, vertical 9:16, no gore, no text, no generic fantasy art.",
            15 => "Cinematic realistic Brazilian folklore scene, {$moments[15]}, inspired by this narration: {$scene->narration}. Low-key lighting, vertical 9:16, no gore, no text, no generic fantasy art.",
            18 => "Cinematic realistic Brazilian folklore scene, {$moments[18]}, inspired by this narration: {$scene->narration}. Low-key lighting, vertical 9:16, no gore, no text, no generic fantasy art.",
        ];
        $response = $this->http->withToken(config('studio.openai.api_key'))->acceptJson()->connectTimeout(10)->timeout(180)->post('https://api.openai.com/v1/images/generations', ['model' => 'gpt-image-1', 'prompt' => $prompts[$scene->position], 'size' => '1024x1536', 'quality' => 'medium', 'output_format' => 'png'])->throw()->json();
        $image = base64_decode((string) data_get($response, 'data.0.b64_json'), true);
        if ($image === false) {
            throw new RuntimeException('A OpenAI não retornou a imagem esperada.');
        }
        $path = "{$directory}/scene-{$scene->position}.png";
        Storage::disk('local')->put($path, $image);
        $scene->update(['asset_metadata' => ['provider' => 'openai-image', 'path' => $path, 'prompt' => $prompts[$scene->position]]]);
    }

    /** @return array{provider: string, configured: bool} */
    public function configuration(): array
    {
        return ['provider' => 'pexels', 'configured' => filled(config('studio.pexels.api_key'))];
    }
}
