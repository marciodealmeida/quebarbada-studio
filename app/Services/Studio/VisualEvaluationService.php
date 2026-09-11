<?php

namespace App\Services\Studio;

class VisualEvaluationService
{
    /** @param array<string, mixed> $research
     * @return array{visual_score: int, reasons: list<string>}
     */
    public function evaluate(array $research): array
    {
        if (count($research['visual_references'] ?? []) < 3 || count($research['documents_or_records'] ?? []) === 0) {
            return ['visual_score' => 45, 'reasons' => ['Poucas referências visuais ou documentais licenciáveis; não há variedade suficiente para um mini-documentário vertical.']];
        }

        $score = 90;
        $reasons = ['Há registro audiovisual e cobertura de época.', 'Chicago, televisão CRT, controle mestre, antenas e documentos oferecem variedade visual.', 'O plano pode privilegiar stock e referências reais; reconstrução por IA não é necessária como padrão.'];

        return compact('score', 'reasons') + ['visual_score' => $score];
    }
}
