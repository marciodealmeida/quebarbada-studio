<?php

namespace App\Services\Studio;

class DocumentaryScoreService
{
    /** @param list<array<string, mixed>> $references
     * @param  array{locations: int, objects: int, people: int, structures: int, documents: int, maps: int, records: int, archives: int, ai_scenes: int, scene_count: int, real_visual_types: int}  $inventory
     * @return array{documentary_score: int, decision: string, approved_references: int}
     */
    public function score(array $references, array $inventory): array
    {
        $approved = collect($references)->where('approved', true)->values();
        $referenceScore = min(30, $approved->count() * 6);
        $qualityScore = min(20, (int) round($approved->avg('visual_score') ?: 0) / 5);
        $subjectScore = min(15, ($inventory['locations'] + $inventory['objects'] + $inventory['people'] + $inventory['structures']) * 3);
        $recordScore = min(15, ($inventory['documents'] + $inventory['maps'] + $inventory['records'] + $inventory['archives']) * 3);
        $independenceScore = min(10, max(0, 10 - $inventory['ai_scenes'] * 3));
        $varietyScore = min(10, $inventory['real_visual_types'] * 2);
        $score = $referenceScore + $qualityScore + $subjectScore + $recordScore + $independenceScore + $varietyScore;
        $decision = $score >= 70 ? 'preferencial' : ($score >= 50 ? 'somente com editorial excepcional' : 'rejeitado para mini-documentário');

        return ['documentary_score' => $score, 'decision' => $decision, 'approved_references' => $approved->count()];
    }

    /** @param list<array<string, mixed>> $topics
     * @return list<array<string, mixed>>
     */
    public function rank(array $topics): array
    {
        return collect($topics)->filter(fn (array $topic): bool => $topic['documentary_score'] >= 70 || ($topic['documentary_score'] >= 50 && $topic['editorial_score'] >= 90))
            ->sortByDesc(fn (array $topic): int => $topic['editorial_score'] + $topic['visual_score'] + $topic['documentary_score'])->values()->all();
    }
}
