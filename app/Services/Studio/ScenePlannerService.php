<?php

namespace App\Services\Studio;

class ScenePlannerService
{
    /** @return list<array{position: int, duration_seconds: int, narration: string, visual_prompt: string, clip_search_terms: list<string>}> */
    public function plan(string $narration, string $topic): array
    {
        $words = preg_split('/\s+/', trim($narration)) ?: [];
        $topic = mb_strtolower($topic);
        $forestTheme = str_contains($topic, 'corpo-seco') || str_contains($topic, 'fulozinha');
        $riverTheme = str_contains($topic, 'cuia');
        $sceneCount = 20;
        $lines = array_map(function (int $position) use ($words, $sceneCount): string {
            $start = (int) floor($position * count($words) / $sceneCount);
            $end = (int) floor(($position + 1) * count($words) / $sceneCount);

            return implode(' ', array_slice($words, $start, $end - $start));
        }, range(0, $sceneCount - 1));

        return array_map(function (string $line, int $index) use ($forestTheme, $riverTheme): array {
            $position = $index + 1;

            return [
                'position' => $position,
                'duration_seconds' => 4,
                'narration' => $line,
                'visual_prompt' => "Cinematic vertical Brazilian folklore atmosphere for scene {$position}, low-key lighting and restrained suspense, 9:16",
                'clip_search_terms' => $forestTheme ? match ($position % 5) {
                    1 => ['dark forest path night'], 2 => ['misty forest trees night'], 3 => ['rain rural road night'], 4 => ['old wooden door shadow'], default => ['dark countryside fog'],
                } : ($riverTheme ? match ($position % 5) {
                    1 => ['dark river fog night'], 2 => ['river water moonlight'], 3 => ['rain riverside night'], 4 => ['empty boat dark water'], default => ['misty river shore'],
                } : match ($position % 5) {
                    1 => ['empty highway night rain'], 2 => ['car headlights fog road'], 3 => ['dark road forest'], 4 => ['rainy windshield night'], default => ['lonely road night'],
                }),
            ];
        }, $lines, array_keys($lines));
    }
}
