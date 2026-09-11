<?php

namespace Tests\Unit\Studio;

use App\Services\Studio\AssetRankingService;
use App\Services\Studio\DocumentaryScoreService;
use App\Services\Studio\RealReferenceService;
use App\Services\Studio\ResearchService;
use App\Services\Studio\ScenePlannerService;
use App\Services\Studio\ScriptGeneratorService;
use App\Services\Studio\VisualEvaluationService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ResearchAndAssetRankingServiceTest extends TestCase
{
    public function test_research_separates_confirmed_facts_hypotheses_and_uncertainties(): void
    {
        $research = app(ResearchService::class)->research(['topic' => 'O sequestro do sinal Max Headroom: a invasão que ninguém assumiu']);

        $this->assertSame('confirmed', $research['key_facts'][0]['status']);
        $this->assertSame('hypothesis', $research['key_facts'][3]['status']);
        $this->assertSame('uncertain', $research['key_facts'][4]['status']);
        $this->assertNotEmpty($research['source_notes']);
    }

    public function test_db_cooper_research_separates_the_confirmed_jump_from_the_unresolved_identity(): void
    {
        $research = app(ResearchService::class)->research(['topic' => 'D. B. Cooper: o passageiro que saltou do avião e desapareceu']);

        $this->assertSame('confirmed', $research['key_facts'][3]['status']);
        $this->assertSame('hypothesis', $research['key_facts'][5]['status']);
        $this->assertSame('uncertain', $research['key_facts'][6]['status']);
        $this->assertCount(4, $research['source_notes']);
    }

    public function test_visual_evaluation_approves_a_case_with_real_records_and_variety(): void
    {
        $research = app(ResearchService::class)->research(['topic' => 'O sequestro do sinal Max Headroom: a invasão que ninguém assumiu']);

        $this->assertSame(90, app(VisualEvaluationService::class)->evaluate($research)['visual_score']);
    }

    public function test_visual_evaluation_rejects_a_topic_without_enough_visual_evidence(): void
    {
        $evaluation = app(VisualEvaluationService::class)->evaluate(['visual_references' => ['one image'], 'documents_or_records' => []]);

        $this->assertSame(45, $evaluation['visual_score']);
    }

    public function test_asset_ranking_rejects_incompatible_assets_and_ranks_the_best_candidate(): void
    {
        $scene = ['required_elements' => ['television', 'static'], 'forbidden_elements' => ['modern flat screen']];
        $candidates = [
            ['id' => 1, 'description' => 'television static in dark room', 'tags' => ['television', 'static'], 'vertical' => true, 'quality_score' => 9, 'motion_score' => 7],
            ['id' => 2, 'description' => 'modern flat screen television static', 'tags' => ['television', 'static', 'modern flat screen'], 'vertical' => true, 'quality_score' => 10, 'motion_score' => 8],
        ];

        $ranked = app(AssetRankingService::class)->rank($scene, $candidates);

        $this->assertCount(1, $ranked);
        $this->assertSame(1, $ranked[0]['id']);
    }

    public function test_asset_ranking_returns_no_candidate_when_fallback_is_required(): void
    {
        $scene = ['required_elements' => ['television'], 'forbidden_elements' => ['modern flat screen']];

        $this->assertSame([], app(AssetRankingService::class)->rank($scene, [['id' => 2, 'description' => 'modern flat screen', 'tags' => [], 'vertical' => true, 'quality_score' => 10, 'motion_score' => 8]]));
    }

    public function test_asset_ranking_rejects_an_intact_airship_when_the_narrated_event_is_fire_and_collapse(): void
    {
        $scene = ['importance' => 5, 'required_elements' => ['Hindenburg'], 'required_event_visuals' => ['flames', 'burning airship', 'collapse'], 'forbidden_elements' => []];
        $candidates = [
            ['id' => 1, 'description' => 'Hindenburg intact at Lakehurst', 'tags' => ['Hindenburg', 'airship'], 'vertical' => true, 'quality_score' => 10, 'motion_score' => 8],
            ['id' => 2, 'description' => 'Hindenburg flames burning airship collapse', 'tags' => ['Hindenburg', 'flames', 'burning airship', 'collapse'], 'vertical' => true, 'quality_score' => 10, 'motion_score' => 8],
        ];

        $ranked = app(AssetRankingService::class)->rank($scene, $candidates);

        $this->assertCount(1, $ranked);
        $this->assertSame(2, $ranked[0]['id']);
        $this->assertSame(100, $ranked[0]['event_coverage_score']);
    }

    public function test_scene_plan_persists_event_requirements_for_the_hindenburg_climax(): void
    {
        $plan = app(ScenePlannerService::class)->plan('As câmeras registraram o incêndio. O dirigível entrou em chamas. A estrutura caiu. Destroços permaneceram em Lakehurst. A causa segue debatida.', 'O desastre do Hindenburg: a queda registrada por câmeras', 'evento histórico documentado');

        $this->assertSame('O Hindenburg entra em chamas e começa a cair.', $plan[0]['narrated_event']);
        $this->assertSame(['flames', 'burning airship', 'collapse'], $plan[0]['required_event_visuals']);
        $this->assertSame(95, $plan[0]['event_coverage_score']);
    }

    public function test_db_cooper_plan_uses_twelve_documentary_scenes_with_event_coverage(): void
    {
        $topic = ['topic' => 'D. B. Cooper: o passageiro que saltou do avião e desapareceu', 'content_type' => 'true crime — fugitivo e investigação sem solução', 'editorial_note' => 'Foco em investigação.'];
        $script = app(ScriptGeneratorService::class)->generate($topic, [], true);
        $plan = app(ScenePlannerService::class)->plan($script['narration'], $topic['topic'], $topic['content_type']);

        $this->assertCount(12, $plan);
        $this->assertSame(84, array_sum(array_column($plan, 'duration_seconds')));
        $this->assertSame('O dinheiro foi encontrado; o homem, nunca.', preg_split('/(?<=[.!?])\s+/u', $script['narration'])[0]);
        $this->assertTrue(app(ScriptGeneratorService::class)->hasRetentiveHook($script['narration']));
        $this->assertTrue(collect($plan)->every(fn (array $scene): bool => $scene['asset_provider'] === 'real-reference' && $scene['event_coverage_score'] >= 85));
    }

    public function test_real_reference_discovery_rejects_protected_subject_even_with_public_domain_label(): void
    {
        Http::preventStrayRequests();
        Http::fake(['https://commons.wikimedia.org/w/api.php*' => Http::response([
            'query' => ['pages' => [[
                'title' => 'File:Max Headroom incident.jpg',
                'fullurl' => 'https://commons.example/file',
                'imageinfo' => [[
                    'url' => 'https://commons.example/media.jpg',
                    'extmetadata' => [
                        'LicenseShortName' => ['value' => 'PD-US'],
                        'LicenseUrl' => ['value' => 'https://creativecommons.org/publicdomain/mark/1.0/'],
                        'Artist' => ['value' => 'Unknown'],
                    ],
                ]],
            ]]],
        ], 200)]);

        $result = app(RealReferenceService::class)->discover(['clip_search_terms' => ['Max Headroom incident'], 'required_elements' => ['Max'], 'asset_provider' => 'pexels']);

        $this->assertSame('stock', $result['decision']);
        $this->assertFalse($result['candidates'][0]['approved']);
        $this->assertStringContainsString('protegido', $result['candidates'][0]['rejection_reason']);
    }

    public function test_real_reference_discovery_approves_a_public_domain_fbi_sketch_that_covers_the_event(): void
    {
        Http::preventStrayRequests();
        Http::fake(['https://commons.wikimedia.org/w/api.php*' => Http::response([
            'query' => ['pages' => [[
                'title' => 'File:D.B. Cooper Composite Sketch B.jpg',
                'fullurl' => 'https://commons.example/cooper-sketch',
                'imageinfo' => [[
                    'url' => 'https://commons.example/cooper-sketch.jpg',
                    'extmetadata' => [
                        'LicenseShortName' => ['value' => 'Public domain'],
                        'Artist' => ['value' => 'Federal Bureau of Investigation'],
                    ],
                ]],
            ]]],
        ], 200)]);

        $result = app(RealReferenceService::class)->discover(['clip_search_terms' => ['D.B. Cooper composite sketch'], 'required_elements' => ['D.B.', 'Cooper'], 'required_event_visuals' => ['composite sketch'], 'importance' => 5, 'asset_provider' => 'real-reference']);

        $this->assertSame('real_reference', $result['decision']);
        $this->assertTrue($result['candidates'][0]['approved']);
        $this->assertTrue($result['candidates'][0]['public_domain']);
        $this->assertSame(100, $result['candidates'][0]['event_coverage_score']);
    }

    public function test_documentary_score_rejects_a_theme_without_approved_references(): void
    {
        $result = app(DocumentaryScoreService::class)->score([], ['locations' => 1, 'objects' => 0, 'people' => 0, 'structures' => 1, 'documents' => 0, 'maps' => 0, 'records' => 0, 'archives' => 0, 'ai_scenes' => 3, 'scene_count' => 8, 'real_visual_types' => 1]);

        $this->assertSame('rejeitado para mini-documentário', $result['decision']);
        $this->assertLessThan(50, $result['documentary_score']);
    }
}
