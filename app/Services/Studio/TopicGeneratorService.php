<?php

namespace App\Services\Studio;

use App\Models\Video;
use RuntimeException;

class TopicGeneratorService
{
    /** @return array{topic: string, content_type: string, editorial_note: string, editorial_score: int, editorial_ranking: list<array<string, mixed>>} */
    public function generate(string $channelSlug): array
    {
        $rankedCandidates = $this->rankedCandidates();
        $usedTopics = Video::query()->whereNotIn('status', ['rejected_duration'])->pluck('topic')->all();
        $candidate = collect($rankedCandidates)
            ->reject(fn (array $candidate): bool => in_array($candidate['topic'], $usedTopics, true))
            ->first();

        if ($candidate === null) {
            throw new RuntimeException('Não há tema inédito acima do limiar editorial.');
        }

        return [...$candidate, 'editorial_ranking' => $rankedCandidates];
    }

    /** @return list<array{topic: string, content_type: string, editorial_note: string, hook: string, scores: array<string, int>, editorial_score: int, retention_verdict: string}> */
    public function rankedCandidates(): array
    {
        return collect($this->candidates())
            ->map(function (array $candidate): array {
                $candidate = $this->withDocumentaryPlan($candidate);
                $score = array_sum($candidate['scores']);

                $documentaryEligible = $candidate['documentary_score'] >= 70 || ($candidate['documentary_score'] >= 50 && $score >= 90);

                return [...$candidate, 'editorial_score' => $score, 'retention_verdict' => $score >= 85 ? 'preferencial: tem força para impedir um swipe nos primeiros 3 segundos' : ($score >= 75 ? 'aceitável: requer gancho rigoroso nos primeiros 3 segundos' : 'rejeitado: não tem força suficiente para feed'), 'documentary_eligible' => $documentaryEligible];
            })
            ->filter(fn (array $candidate): bool => $candidate['editorial_score'] >= 75 && $candidate['documentary_eligible'])
            ->sortByDesc(fn (array $candidate): int => $candidate['editorial_score'] + $candidate['visual_score'] + $candidate['documentary_score'] + $candidate['event_coverage_potential'])
            ->values()
            ->all();
    }

    /** @return list<array<string, mixed>> */
    public function dryRunComparison(): array
    {
        $usedTopics = Video::query()->whereNotIn('status', ['rejected_duration'])->pluck('topic')->all();

        return collect($this->rankedCandidates())->reject(fn (array $candidate): bool => in_array($candidate['topic'], $usedTopics, true))->values()->map(fn (array $candidate): array => [
            'topic' => $candidate['topic'],
            'content_type' => $candidate['content_type'],
            'editorial_score' => $candidate['editorial_score'],
            'visual_score' => $candidate['visual_score'],
            'documentary_score' => $candidate['documentary_score'],
            'event_coverage_potential' => $candidate['event_coverage_potential'],
            'real_material_estimate' => $candidate['real_material_estimate'],
            'main_visual_risk' => $candidate['main_visual_risk'],
            'estimated_cost_usd' => $candidate['estimated_cost_usd'],
            'selection_score' => $candidate['editorial_score'] + $candidate['visual_score'] + $candidate['documentary_score'] + $candidate['event_coverage_potential'],
        ])->all();
    }

    /** @return list<array{topic: string, content_type: string, editorial_note: string, hook: string, scores: array<string, int>}> */
    private function candidates(): array
    {
        return [
            ['topic' => 'O desastre do Hindenburg: a queda registrada por câmeras', 'content_type' => 'evento histórico documentado', 'editorial_note' => 'Tratar o desastre de 1937 com precisão histórica e sem imagens gráficas; diferenciar registros da causa investigada.', 'hook' => 'O maior dirigível do mundo caiu diante das câmeras.', 'visual_score' => 95, 'documentary_score' => 90, 'scores' => ['immediate_hook' => 25, 'danger_or_strangeness' => 19, 'narrative_progression' => 15, 'climax_payoff' => 15, 'visual_potential' => 10, 'mystery_strength' => 6, 'originality_vs_history' => 4]],
            ['topic' => 'O incidente do passo Dyatlov: nove barracas abertas por dentro', 'content_type' => 'caso histórico documentado', 'editorial_note' => 'Apresentar os fatos documentados da expedição de 1959 e distinguir hipóteses de conclusões. Evitar gore e especulação apresentada como prova.', 'hook' => 'Nove pessoas cortaram a própria barraca e fugiram para a neve.', 'visual_score' => 88, 'documentary_score' => 55, 'scores' => ['immediate_hook' => 25, 'danger_or_strangeness' => 20, 'narrative_progression' => 15, 'climax_payoff' => 14, 'visual_potential' => 9, 'mystery_strength' => 10, 'originality_vs_history' => 4]],
            ['topic' => 'O sequestro do sinal Max Headroom: a invasão que ninguém assumiu', 'content_type' => 'mistério da internet documentado', 'editorial_note' => 'Basear-se nas duas invasões reais de sinal de TV em Chicago, em 1987. Não identificar autores sem fonte confiável.', 'hook' => 'Alguém invadiu a TV ao vivo — e nunca foi encontrado.', 'visual_score' => 94, 'documentary_score' => 22, 'scores' => ['immediate_hook' => 25, 'danger_or_strangeness' => 18, 'narrative_progression' => 15, 'climax_payoff' => 15, 'visual_potential' => 10, 'mystery_strength' => 10, 'originality_vs_history' => 5]],
            ['topic' => 'O farol das Ilhas Flannan: três guardiões desapareceram sem deixar rastro', 'content_type' => 'desaparecimento real documentado', 'editorial_note' => 'Distinguir os registros históricos das versões posteriores dramatizadas. Não inventar uma explicação para o desaparecimento.', 'hook' => 'Três homens entraram num farol. Nenhum saiu dele.', 'visual_score' => 82, 'documentary_score' => 63, 'scores' => ['immediate_hook' => 24, 'danger_or_strangeness' => 18, 'narrative_progression' => 14, 'climax_payoff' => 13, 'visual_potential' => 9, 'mystery_strength' => 9, 'originality_vs_history' => 5]],
            ['topic' => 'A estação UVB-76: o rádio que transmite ruído há décadas', 'content_type' => 'mistério de transmissão documentado', 'editorial_note' => 'Explicar que a estação foi registrada por radioamadores e que sua finalidade não é confirmada publicamente.', 'hook' => 'Um rádio russo transmite o mesmo ruído há décadas.', 'visual_score' => 72, 'documentary_score' => 62, 'scores' => ['immediate_hook' => 20, 'danger_or_strangeness' => 16, 'narrative_progression' => 12, 'climax_payoff' => 10, 'visual_potential' => 8, 'mystery_strength' => 10, 'originality_vs_history' => 4]],
            ['topic' => 'O sequestro Lindbergh: a escada que levou a polícia até um homem', 'content_type' => 'true crime — crime histórico resolvido', 'editorial_note' => 'Focar na investigação, nas provas e no julgamento. Não mostrar violência contra a criança nem transformar o autor no centro da história.', 'hook' => 'Uma escada deixada numa casa levou a polícia até um nome.', 'visual_score' => 92, 'documentary_score' => 88, 'event_coverage_potential' => 85, 'real_material_estimate' => ['approved_references' => 9, 'real_scenes' => 8, 'stock_scenes' => 2, 'ai_scenes' => 0], 'main_visual_risk' => 'Evitar dramatizar o sequestro ou usar imagens de crianças como ilustração genérica.', 'estimated_cost_usd' => 0.72, 'scores' => ['immediate_hook' => 24, 'danger_or_strangeness' => 18, 'narrative_progression' => 15, 'climax_payoff' => 14, 'visual_potential' => 9, 'mystery_strength' => 9, 'originality_vs_history' => 5]],
            ['topic' => 'D. B. Cooper: o passageiro que saltou do avião e desapareceu', 'content_type' => 'true crime — fugitivo e investigação sem solução', 'editorial_note' => 'Tratar o sequestrador como objeto da investigação, sem glamour. Distinguir o que o FBI documentou do que continua desconhecido.', 'hook' => 'Ele saltou de um avião com dinheiro — e nunca foi encontrado.', 'visual_score' => 89, 'documentary_score' => 84, 'event_coverage_potential' => 82, 'real_material_estimate' => ['approved_references' => 8, 'real_scenes' => 7, 'stock_scenes' => 3, 'ai_scenes' => 0], 'main_visual_risk' => 'Não há registro visual direto do salto; documentos e objetos devem substituir reconstituições sensacionalistas.', 'estimated_cost_usd' => 0.72, 'scores' => ['immediate_hook' => 25, 'danger_or_strangeness' => 18, 'narrative_progression' => 15, 'climax_payoff' => 14, 'visual_potential' => 9, 'mystery_strength' => 10, 'originality_vs_history' => 4]],
            ['topic' => 'O Golden State Killer: décadas de crimes resolvidas por DNA', 'content_type' => 'true crime — crimes seriais e investigação forense', 'editorial_note' => 'Focar no trabalho investigativo e na identificação por genealogia genética, sem descrever violência ou glorificar o criminoso.', 'hook' => 'Décadas depois, uma pista de DNA levou a polícia até a porta certa.', 'visual_score' => 82, 'documentary_score' => 70, 'event_coverage_potential' => 83, 'real_material_estimate' => ['approved_references' => 7, 'real_scenes' => 7, 'stock_scenes' => 3, 'ai_scenes' => 0], 'main_visual_risk' => 'Fotos de cenas de crime e de vítimas não devem ser usadas; a evidência forense precisa ser real ou claramente abstrata.', 'estimated_cost_usd' => 0.72, 'scores' => ['immediate_hook' => 24, 'danger_or_strangeness' => 17, 'narrative_progression' => 15, 'climax_payoff' => 15, 'visual_potential' => 8, 'mystery_strength' => 9, 'originality_vs_history' => 5]],
            ['topic' => 'O Grande Roubo do Trem: a investigação que encontrou o esconderijo', 'content_type' => 'true crime — caso criminal histórico', 'editorial_note' => 'Priorizar a investigação e os registros do caso; não romantizar os envolvidos nem ensinar métodos de crime.', 'hook' => 'Um trem foi assaltado — e o erro dos criminosos ficou dentro do esconderijo.', 'visual_score' => 87, 'documentary_score' => 80, 'event_coverage_potential' => 81, 'real_material_estimate' => ['approved_references' => 8, 'real_scenes' => 7, 'stock_scenes' => 3, 'ai_scenes' => 0], 'main_visual_risk' => 'O roubo em si pode acabar ilustrado com trem genérico; evidências e arquivo precisam conduzir a sequência.', 'estimated_cost_usd' => 0.72, 'scores' => ['immediate_hook' => 24, 'danger_or_strangeness' => 17, 'narrative_progression' => 15, 'climax_payoff' => 14, 'visual_potential' => 9, 'mystery_strength' => 8, 'originality_vs_history' => 5]],
            ['topic' => 'O caso Zodiac: as cartas que a investigação nunca decifrou por completo', 'content_type' => 'true crime — assassinatos reais sem solução', 'editorial_note' => 'Não descrever violência nem tratar o criminoso como ícone. O foco são as cartas, os documentos e as limitações da investigação.', 'hook' => 'As cartas chegaram à polícia. O autor nunca foi identificado.', 'visual_score' => 78, 'documentary_score' => 52, 'event_coverage_potential' => 70, 'real_material_estimate' => ['approved_references' => 5, 'real_scenes' => 5, 'stock_scenes' => 4, 'ai_scenes' => 1], 'main_visual_risk' => 'Os eventos violentos não têm cobertura visual licenciada suficiente; documentos devem substituir dramatização.', 'estimated_cost_usd' => 1.02, 'scores' => ['immediate_hook' => 24, 'danger_or_strangeness' => 19, 'narrative_progression' => 15, 'climax_payoff' => 14, 'visual_potential' => 8, 'mystery_strength' => 10, 'originality_vs_history' => 5]],
        ];
    }

    /** @param array<string, mixed> $candidate
     * @return array<string, mixed>
     */
    private function withDocumentaryPlan(array $candidate): array
    {
        return [...$candidate, 'event_coverage_potential' => $candidate['event_coverage_potential'] ?? match ($candidate['topic']) {
            'O desastre do Hindenburg: a queda registrada por câmeras' => 96,
            'O incidente do passo Dyatlov: nove barracas abertas por dentro' => 74,
            'O sequestro do sinal Max Headroom: a invasão que ninguém assumiu' => 68,
            'O farol das Ilhas Flannan: três guardiões desapareceram sem deixar rastro' => 76,
            default => 65,
        }, 'real_material_estimate' => $candidate['real_material_estimate'] ?? match ($candidate['topic']) {
            'O desastre do Hindenburg: a queda registrada por câmeras' => ['approved_references' => 4, 'real_scenes' => 8, 'stock_scenes' => 0, 'ai_scenes' => 0],
            default => ['approved_references' => 0, 'real_scenes' => 0, 'stock_scenes' => 8, 'ai_scenes' => 0],
        }, 'main_visual_risk' => $candidate['main_visual_risk'] ?? 'Material documental real insuficiente para mostrar cada evento narrado.', 'estimated_cost_usd' => $candidate['estimated_cost_usd'] ?? 0.72];
    }
}
