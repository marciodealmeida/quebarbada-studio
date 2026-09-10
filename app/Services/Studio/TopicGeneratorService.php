<?php

namespace App\Services\Studio;

use App\Models\Video;

class TopicGeneratorService
{
    /** @return array{topic: string, content_type: string, editorial_note: string} */
    public function generate(string $channelSlug): array
    {
        $topics = [
            ['topic' => 'A lenda do Corpo-Seco e suas versões no interior do Brasil', 'content_type' => 'lenda folclórica', 'editorial_note' => 'Apresentar como lenda folclórica regional, sem afirmar que a criatura exista ou que relatos sejam comprovados.'],
            ['topic' => 'A lenda da Mulher de Branco nas estradas brasileiras', 'content_type' => 'lenda urbana', 'editorial_note' => 'Apresentar como lenda urbana de estrada, sem afirmar aparições ou acidentes sobrenaturais como fatos.'],
            ['topic' => 'A lenda da Cabeça de Cuia no Rio Parnaíba', 'content_type' => 'lenda folclórica', 'editorial_note' => 'Apresentar como lenda folclórica piauiense, sem tratar versões populares como fatos comprovados.'],
            ['topic' => 'A lenda da Comadre Fulozinha nas matas do Nordeste', 'content_type' => 'lenda folclórica', 'editorial_note' => 'Apresentar como personagem do folclore nordestino, sem afirmar encontros sobrenaturais como reais.'],
            ['topic' => 'A lenda da Pisadeira nas histórias brasileiras', 'content_type' => 'relato folclórico', 'editorial_note' => 'Diferenciar a lenda de experiências do sono e não afirmar causas sobrenaturais como comprovadas.'],
        ];

        $usedTopics = Video::query()->pluck('topic')->all();
        foreach ($topics as $topic) {
            if (! in_array($topic['topic'], $usedTopics, true)) {
                return $topic;
            }
        }

        throw new \RuntimeException('Não há tema inédito disponível no catálogo editorial.');
    }
}
