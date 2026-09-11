<?php

namespace App\Services\Studio;

use RuntimeException;

class ResearchService
{
    /** @return array<string, mixed> */
    public function research(array $topic): array
    {
        if (str_contains(mb_strtolower($topic['topic']), 'hindenburg')) {
            return $this->hindenburgResearch();
        }
        if (str_contains(mb_strtolower($topic['topic']), 'd. b. cooper')) {
            return $this->dbCooperResearch();
        }
        if (! str_contains(mb_strtolower($topic['topic']), 'max headroom')) {
            throw new RuntimeException('Ainda não há dossiê factual aprovado para este tema.');
        }

        return [
            'title' => 'O sequestro do sinal Max Headroom',
            'summary' => 'Em 22 de novembro de 1987, duas emissoras de Chicago tiveram seus sinais invadidos por uma transmissão pirata com uma figura mascarada. Os responsáveis não foram identificados publicamente.',
            'key_facts' => [
                ['claim' => 'WGN-TV e WTTW foram interrompidas na mesma noite em Chicago.', 'status' => 'confirmed'],
                ['claim' => 'A primeira invasão ocorreu durante o noticiário da WGN; outra atingiu a WTTW durante Doctor Who.', 'status' => 'confirmed'],
                ['claim' => 'A figura usava máscara semelhante a Max Headroom diante de um fundo metálico.', 'status' => 'confirmed'],
                ['claim' => 'A técnica provável envolveu sobrepor um enlace de micro-ondas.', 'status' => 'hypothesis'],
                ['claim' => 'Os responsáveis jamais foram identificados publicamente.', 'status' => 'uncertain'],
            ],
            'timeline' => [['date' => '1987-11-22', 'event' => 'WGN-TV é interrompida durante o noticiário.'], ['date' => '1987-11-22', 'event' => 'WTTW é interrompida durante Doctor Who.']],
            'locations' => ['Chicago, Illinois', 'WGN-TV', 'WTTW-TV'],
            'people' => ['Figura mascarada não identificada'],
            'organizations' => ['WGN-TV', 'WTTW-TV', 'Federal Communications Commission'],
            'documents_or_records' => ['Cobertura contemporânea da UPI de 23 e 24 de novembro de 1987', 'Electronic Media, edição de 30 de novembro de 1987', 'Registros públicos de pedido FOIA à FCC'],
            'visual_references' => ['televisão CRT dos anos 1980', 'controle mestre de emissora', 'skyline de Chicago', 'antena/enlace de micro-ondas', 'manchetes e documentos de arquivo'],
            'unresolved_questions' => ['Quem produziu a transmissão?', 'Como os responsáveis obtiveram o equipamento e o posicionamento necessários?', 'Por que escolheram as duas emissoras?'],
            'source_notes' => [
                ['title' => 'UPI, 23 Nov 1987', 'url' => 'https://www.upi.com/Archives/1987/11/23/A-video-pirate-struck-two-Chicago-television-stations-briefly/5688564642000/', 'type' => 'contemporaneous reporting'],
                ['title' => 'WTTW Chicago retrospective', 'url' => 'https://www.wttw.com/jayschicago/max-headroom-attack', 'type' => 'station retrospective'],
                ['title' => 'Electronic Media, 30 Nov 1987', 'url' => 'https://www.worldradiohistory.com/Archive-All-Communications/Electronic-Media/80s/87/Electronic-Media-1987-11-30.pdf', 'type' => 'trade press archive'],
            ],
            'confidence_notes' => ['Duração exata da primeira interrupção varia entre fontes; o roteiro não deve afirmar um número único.', 'A autoria e o método preciso permanecem sem confirmação pública.'],
        ];
    }

    /** @return array<string, mixed> */
    private function hindenburgResearch(): array
    {
        return ['title' => 'O desastre do Hindenburg', 'summary' => 'O dirigível alemão Hindenburg pegou fogo ao tentar pousar na Naval Air Station de Lakehurst, em Nova Jersey, em 6 de maio de 1937. O desastre foi registrado por câmeras e rádio; sua causa inicial permanece debatida.', 'key_facts' => [['claim' => 'O Hindenburg pegou fogo durante a aproximação para pouso em Lakehurst em 6 de maio de 1937.', 'status' => 'confirmed'], ['claim' => 'O desastre foi registrado por fotografia, filme e reportagem de rádio.', 'status' => 'confirmed'], ['claim' => 'O fogo consumiu a aeronave em pouco mais de meio minuto.', 'status' => 'confirmed'], ['claim' => 'A causa inicial da ignição continua debatida entre hipóteses técnicas.', 'status' => 'uncertain']], 'timeline' => [['date' => '1937-05-06', 'event' => 'O Hindenburg se aproxima para pouso em Lakehurst.'], ['date' => '1937-05-06', 'event' => 'O incêndio destrói o dirigível diante de testemunhas e câmeras.']], 'locations' => ['Naval Air Station Lakehurst, Nova Jersey'], 'people' => ['Passageiros, tripulação e equipes de solo'], 'organizations' => ['Deutsche Zeppelin-Reederei', 'United States Department of Commerce'], 'documents_or_records' => ['Filmagem do desastre', 'fotografias do Bundesarchiv', 'relatórios e registros de investigação'], 'visual_references' => ['filmagem do desastre', 'construção do dirigível', 'interior do salão', 'destroço preservado', 'diagrama CC0'], 'unresolved_questions' => ['Qual foi o gatilho exato da ignição?'], 'source_notes' => [], 'confidence_notes' => ['O roteiro não atribui uma causa definitiva ao incêndio.']];
    }

    /** @return array<string, mixed> */
    private function dbCooperResearch(): array
    {
        return [
            'title' => 'D. B. Cooper: o sequestrador que desapareceu',
            'summary' => 'Em 24 de novembro de 1971, um homem que usou o nome Dan Cooper comprou uma passagem para o voo 305 da Northwest Orient, entre Portland e Seattle. Após exigir US$ 200 mil e paraquedas, ele libertou passageiros em Seattle, decolou novamente e saltou do Boeing 727. Ele nunca foi identificado publicamente.',
            'key_facts' => [
                ['claim' => 'O homem comprou em dinheiro uma passagem só de ida de Portland para Seattle sob o nome Dan Cooper.', 'status' => 'confirmed'],
                ['claim' => 'Durante o voo 305, ele entregou uma nota à comissária e exigiu US$ 200 mil em notas de US$ 20 e quatro paraquedas.', 'status' => 'confirmed'],
                ['claim' => 'Em Seattle, 36 passageiros foram liberados em troca do dinheiro e dos paraquedas.', 'status' => 'confirmed'],
                ['claim' => 'Depois da nova decolagem, ele saltou pela escada traseira do Boeing 727 em algum ponto entre Seattle e Reno.', 'status' => 'confirmed'],
                ['claim' => 'Em 1980, foram encontradas notas deterioradas que correspondiam aos números de série de parte do resgate.', 'status' => 'confirmed'],
                ['claim' => 'Ele provavelmente morreu no salto.', 'status' => 'hypothesis'],
                ['claim' => 'A identidade, o ponto exato do salto e o destino da maior parte do dinheiro seguem sem confirmação pública.', 'status' => 'uncertain'],
            ],
            'timeline' => [
                ['date' => '1971-11-24', 'event' => 'Dan Cooper compra a passagem e embarca no voo 305 em Portland.'],
                ['date' => '1971-11-24', 'event' => 'Ele exige dinheiro e quatro paraquedas durante o voo.'],
                ['date' => '1971-11-24', 'event' => 'Em Seattle, passageiros são trocados pelo resgate e o avião decola novamente.'],
                ['date' => '1971-11-24', 'event' => 'Cooper salta do Boeing 727 entre Seattle e Reno.'],
                ['date' => '1980', 'event' => 'Parte das notas do resgate é encontrada e ligada pelos números de série.'],
                ['date' => '2016-07-12', 'event' => 'O FBI redireciona recursos do caso para outras prioridades investigativas.'],
            ],
            'locations' => ['Portland, Oregon', 'Seattle, Washington', 'rota entre Seattle e Reno, Nevada', 'rio Columbia, área de Tina Bar'],
            'people' => ['Dan Cooper, nome usado pelo sequestrador', 'comissárias e tripulação do voo 305', 'agentes do FBI', 'menino que encontrou parte das notas em 1980'],
            'organizations' => ['Northwest Orient Airlines', 'Federal Bureau of Investigation', 'U.S. Attorney’s Office for the Western District of Washington'],
            'documents_or_records' => ['bilhete original do voo 305', 'retratos falados do FBI', 'arquivos NORJAK do FBI Vault', 'U.S. Attorney’s Case File CR-0451 no National Archives', 'registro dos números de série das notas recuperadas'],
            'visual_references' => ['retratos falados do FBI', 'bilhete de passagem original', 'gravata deixada no avião', 'paraquedas e bolsa preservados pelo FBI', 'notas do resgate recuperadas em 1980', 'documentos NORJAK e arquivo do promotor'],
            'unresolved_questions' => ['Qual era a identidade do homem?', 'Em que ponto ele saltou?', 'Ele sobreviveu?', 'Onde está o restante do resgate?'],
            'source_notes' => [
                ['title' => 'FBI — D.B. Cooper Hijacking', 'url' => 'https://www.fbi.gov/history/cases-and-criminals/db-cooper-hijacking', 'type' => 'primary agency history and artifact record'],
                ['title' => 'FBI — D.B. Cooper Plane Ticket', 'url' => 'https://www.fbi.gov/history/artifacts/d-b-cooper-plane-ticket', 'type' => 'primary artifact record'],
                ['title' => 'FBI Vault — D. B. Cooper', 'url' => 'https://vault.fbi.gov/D-B-Cooper%20', 'type' => 'FOIA investigative records'],
                ['title' => 'National Archives — U.S. Attorney Case File CR-0451', 'url' => 'https://www.archives.gov/seattle/highlights/d-b-cooper', 'type' => 'federal archival record'],
            ],
            'confidence_notes' => ['O nome usado na passagem era Dan Cooper; “D.B. Cooper” foi popularizado pela imprensa e não identifica o sequestrador.', 'A investigação não confirma que ele morreu, sobreviveu ou que qualquer suspeito específico fosse o sequestrador.', 'A nota original não deve ser citada ou recriada como se seu texto estivesse completo no registro público.'],
        ];
    }
}
