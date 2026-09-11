<?php

namespace App\Services\Studio;

class AssetRankingService
{
    /** @param array<string, mixed> $scene
     * @param  list<array<string, mixed>>  $candidates
     * @param  list<int>  $usedIds
     * @return list<array<string, mixed>>
     */
    public function rank(array $scene, array $candidates, array $usedIds = []): array
    {
        return collect($candidates)->reject(fn (array $candidate): bool => in_array($candidate['id'] ?? null, $usedIds, true))
            ->map(function (array $candidate) use ($scene): array {
                $haystack = mb_strtolower(($candidate['description'] ?? '').' '.implode(' ', $candidate['tags'] ?? []));
                $requiredElements = $scene['required_elements'] ?? [];
                $requiredEvents = $scene['required_event_visuals'] ?? [];
                $required = collect($requiredElements)->filter(fn (string $element): bool => str_contains($haystack, mb_strtolower($element)))->count();
                $eventMatches = collect($requiredEvents)->filter(fn (string $event): bool => str_contains($haystack, mb_strtolower($event)))->count();
                $eventCoverageScore = $requiredEvents === [] ? 100 : (int) round($eventMatches * 100 / count($requiredEvents));
                $forbidden = collect($scene['forbidden_elements'] ?? [])->contains(fn (string $element): bool => str_contains($haystack, mb_strtolower($element)));
                $score = ($forbidden ? 0 : 45) + $required * 15 + (($candidate['vertical'] ?? false) ? 12 : 0) + min(10, (int) ($candidate['quality_score'] ?? 0)) + min(8, (int) ($candidate['motion_score'] ?? 0));
                $importantScene = (int) ($scene['importance'] ?? 3) >= 4;

                return [...$candidate, 'asset_score' => $score, 'event_coverage_score' => $eventCoverageScore, 'compatible' => ! $forbidden && $required === count($requiredElements) && (! $importantScene || $eventCoverageScore >= 80)];
            })->filter(fn (array $candidate): bool => $candidate['compatible'] && $candidate['asset_score'] >= 75)->sortByDesc('asset_score')->values()->all();
    }
}
