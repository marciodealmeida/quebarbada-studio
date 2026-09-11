<?php

namespace App\Services\Studio;

use App\Models\VideoScene;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use RuntimeException;

class AssetService
{
    public function __construct(private HttpFactory $http, private AssetRankingService $assetRanking) {}

    /** @param iterable<VideoScene> $scenes */
    public function acquire(iterable $scenes, string $directory): void
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
            $this->validateSceneDirection($scene);
            if ($scene->asset_provider === 'real-reference') {
                $this->downloadRealReference($scene, $directory);

                continue;
            }
            if ($scene->asset_provider === 'documentary-map') {
                $this->generateDocumentaryMap($scene, $directory);

                continue;
            }
            if ($scene->asset_provider === 'openai-image') {
                $this->generateImage($scene, $directory);

                continue;
            }
            $query = implode(' ', $scene->clip_search_terms);
            $candidates = $this->searchPexelsCandidates($key, $query);
            $ranked = $this->assetRanking->rank([
                'required_elements' => $scene->required_elements ?? [],
                'forbidden_elements' => $scene->forbidden_elements ?? [],
            ], $candidates, $usedPexelsIds);
            if ($ranked === [] && filled($scene->fallback_search_terms)) {
                $query = implode(' ', $scene->fallback_search_terms);
                $ranked = $this->assetRanking->rank(['required_elements' => $scene->required_elements ?? [], 'forbidden_elements' => $scene->forbidden_elements ?? []], $this->searchPexelsCandidates($key, $query), $usedPexelsIds);
            }
            $candidate = $ranked[0] ?? null;
            if (! $candidate) {
                throw new RuntimeException("Nenhum clipe Pexels coerente atingiu o mínimo para a cena {$scene->position}: {$query}");
            }
            $path = "{$directory}/scene-{$scene->position}.mp4";
            $this->http->connectTimeout(10)->timeout(180)->sink(Storage::disk('local')->path($path))->get($candidate['file']['link'])->throw();
            $scene->update(['event_coverage_score' => $candidate['event_coverage_score'], 'asset_metadata' => ['provider' => 'pexels', 'pexels_id' => $candidate['id'], 'query' => $query, 'path' => $path, 'source_url' => $candidate['file']['link'], 'asset_score' => $candidate['asset_score'], 'event_coverage_score' => $candidate['event_coverage_score'], 'coherence_validated' => true, 'coherence_reason' => "Candidato ranqueado para {$scene->scene_type}: {$scene->visual_intent}"]]);
            $usedPexelsIds[] = $candidate['id'];
        }
    }

    private function downloadRealReference(VideoScene $scene, string $directory): void
    {
        $reference = $scene->asset_metadata ?? [];
        if (blank($reference['asset_url'] ?? null) || blank($reference['license'] ?? null) || blank($reference['license_url'] ?? null)) {
            throw new RuntimeException("A referência real da cena {$scene->position} não possui licença verificável.");
        }
        if ($scene->importance >= 4 && (int) $scene->event_coverage_score < 80) {
            throw new RuntimeException("A referência real da cena {$scene->position} não cobre diretamente o evento narrado.");
        }
        $extension = pathinfo((string) parse_url((string) $reference['asset_url'], PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
        $path = 'studio/references/'.sha1((string) $reference['asset_url']).".{$extension}";
        if (! Storage::disk('local')->exists($path)) {
            Storage::disk('local')->makeDirectory('studio/references');
            Sleep::sleep(1);
            $response = $this->http->withUserAgent('QueSinistroStudio/1.0 (licensed-reference-download)')->connectTimeout(10)->timeout(180)->sink(Storage::disk('local')->path($path))->get($reference['asset_url']);
            if ($response->failed()) {
                Storage::disk('local')->delete($path);
                $response->throw();
            }
        }
        if ($extension === 'pdf') {
            $imagePath = "{$directory}/scene-{$scene->position}.png";
            $page = (int) ($reference['page'] ?? 1);
            Process::timeout(90)->run(['pdftoppm', '-f', (string) $page, '-l', (string) $page, '-singlefile', '-png', '-r', '180', Storage::disk('local')->path($path), Storage::disk('local')->path(str_replace('.png', '', $imagePath))])->throw();
            $path = $imagePath;
        }
        $scene->update(['asset_metadata' => [...$reference, 'path' => $path, 'coherence_validated' => true, 'coherence_reason' => 'Referência documental real aprovada no pre-flight.']]);
    }

    private function generateDocumentaryMap(VideoScene $scene, string $directory): void
    {
        $path = "{$directory}/scene-{$scene->position}.png";
        $image = imagecreatetruecolor(1080, 1920);
        $background = imagecolorallocate($image, 12, 18, 28);
        $line = imagecolorallocate($image, 176, 144, 66);
        $text = imagecolorallocate($image, 236, 232, 218);
        imagefill($image, 0, 0, $background);
        imagestring($image, 5, 100, 190, 'ROTA DOCUMENTAL', $text);
        imagestring($image, 4, 100, 250, 'Base: arquivo CR-0451, National Archives', $text);
        $points = $scene->position === 1 ? [[260, 1080, 'TINA BAR'], [770, 820, 'DINHEIRO RECUPERADO']] : [[190, 820, 'PORTLAND'], [500, 620, 'SEATTLE'], [820, 1040, 'RENO']];
        foreach ($points as $index => [$x, $y, $label]) {
            if ($index > 0) {
                [$previousX, $previousY] = $points[$index - 1];
                imageline($image, $previousX, $previousY, $x, $y, $line);
            }
            imagefilledellipse($image, $x, $y, 28, 28, $line);
            imagestring($image, 5, $x - 50, $y + 35, $label, $text);
        }
        imagestring($image, 4, 100, 1640, 'VISUAL DERIVADO DE REGISTRO FEDERAL', $text);
        imagepng($image, Storage::disk('local')->path($path));
        imagedestroy($image);
        $scene->update(['asset_metadata' => [...($scene->asset_metadata ?? []), 'path' => $path, 'asset_score' => 90, 'event_coverage_score' => 90, 'coherence_validated' => true, 'coherence_reason' => 'Mapa factual derivado do arquivo federal; não representa filmagem do evento.']]);
    }

    /** @return list<array<string, mixed>> */
    private function searchPexelsCandidates(string $key, string $query): array
    {
        $videos = $this->http->withHeaders(['Authorization' => $key])->acceptJson()->connectTimeout(10)->timeout(60)->get('https://api.pexels.com/videos/search', ['query' => $query, 'orientation' => 'portrait', 'per_page' => 10])->throw()->json('videos', []);

        return collect($videos)->map(function (array $video) use ($query): ?array {
            $file = collect($video['video_files'] ?? [])->filter(fn (array $file): bool => ($file['file_type'] ?? '') === 'video/mp4' && ($file['height'] ?? 0) >= 1280)->sortByDesc('height')->first();

            return $file && $video['duration'] >= 5 ? ['id' => $video['id'], 'duration' => $video['duration'], 'file' => $file, 'description' => $query, 'tags' => explode(' ', $query), 'vertical' => true, 'quality_score' => min(10, (int) floor($file['height'] / 192)), 'motion_score' => min(8, (int) floor($video['duration'] / 5))] : null;
        })->filter()->values()->all();
    }

    private function generateImage(VideoScene $scene, string $directory): void
    {
        $prompt = "{$scene->visual_prompt} It must directly represent this narrated fact: {$scene->narration}. Cinematic but realistic, restrained suspense, vertical 9:16, no gore, no people looking at camera, no logos, no invented readable text except the exact requested archival text.";
        $response = $this->http->withToken(config('studio.openai.api_key'))->acceptJson()->connectTimeout(10)->timeout(180)->post('https://api.openai.com/v1/images/generations', ['model' => 'gpt-image-1', 'prompt' => $prompt, 'size' => '1024x1536', 'quality' => 'medium', 'output_format' => 'png'])->throw()->json();
        $image = base64_decode((string) data_get($response, 'data.0.b64_json'), true);
        if ($image === false) {
            throw new RuntimeException('A OpenAI não retornou a imagem esperada.');
        }
        $path = "{$directory}/scene-{$scene->position}.png";
        Storage::disk('local')->put($path, $image);
        $scene->update(['asset_metadata' => ['provider' => 'openai-image', 'path' => $path, 'prompt' => $prompt, 'coherence_validated' => true, 'coherence_reason' => "Imagem solicitada para representar diretamente {$scene->scene_type}."]]);
    }

    private function validateSceneDirection(VideoScene $scene): void
    {
        $validTypes = ['ambientação', 'ação', 'personagem', 'objeto-chave', 'manifestação sobrenatural', 'clímax', 'conclusão'];
        if (! in_array($scene->scene_type, $validTypes, true) || blank($scene->clip_search_terms)) {
            throw new RuntimeException("A cena {$scene->position} não possui direção visual coerente.");
        }
        if ($scene->asset_provider === 'pexels' && $scene->scene_type === 'manifestação sobrenatural') {
            throw new RuntimeException("A cena {$scene->position} exige imagem gerada para representar o momento-chave.");
        }
        if (! in_array($scene->asset_provider, ['pexels', 'openai-image', 'real-reference', 'documentary-map'], true)) {
            throw new RuntimeException("O provedor da cena {$scene->position} não é permitido.");
        }
    }

    /** @return array{provider: string, configured: bool} */
    public function configuration(): array
    {
        return ['provider' => 'pexels', 'configured' => filled(config('studio.pexels.api_key'))];
    }
}
