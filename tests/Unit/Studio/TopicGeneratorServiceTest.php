<?php

namespace Tests\Unit\Studio;

use App\Services\Studio\TopicGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TopicGeneratorServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_ranks_feed_candidates_and_rejects_static_curiosity_below_threshold(): void
    {
        $rankedCandidates = app(TopicGeneratorService::class)->rankedCandidates();

        $this->assertSame('O desastre do Hindenburg: a queda registrada por câmeras', $rankedCandidates[0]['topic']);
        $this->assertSame(94, $rankedCandidates[0]['editorial_score']);
        $this->assertSame('preferencial: tem força para impedir um swipe nos primeiros 3 segundos', $rankedCandidates[0]['retention_verdict']);
        $this->assertSame(90, $rankedCandidates[0]['documentary_score']);
        $this->assertContains('D. B. Cooper: o passageiro que saltou do avião e desapareceu', collect($rankedCandidates)->pluck('topic')->all());
        $this->assertSame(96, $rankedCandidates[0]['event_coverage_potential']);
    }

    public function test_selects_the_highest_scoring_unused_candidate(): void
    {
        $topic = app(TopicGeneratorService::class)->generate('que-sinistro');

        $this->assertSame('O desastre do Hindenburg: a queda registrada por câmeras', $topic['topic']);
        $this->assertSame(94, $topic['editorial_score']);
    }

    public function test_dry_run_comparison_includes_true_crime_documentary_and_event_scores(): void
    {
        $comparison = app(TopicGeneratorService::class)->dryRunComparison();
        $dbCooper = collect($comparison)->firstWhere('topic', 'D. B. Cooper: o passageiro que saltou do avião e desapareceu');

        $this->assertSame(84, $dbCooper['documentary_score']);
        $this->assertSame(82, $dbCooper['event_coverage_potential']);
        $this->assertSame(7, $dbCooper['real_material_estimate']['real_scenes']);
    }
}
