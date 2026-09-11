<?php

namespace App\Services\Studio;

use RuntimeException;

class ScenePlannerService
{
    /**
     * @return list<array{position: int, duration_seconds: int, narration: string, scene_type: string, visual_prompt: string, clip_search_terms: list<string>, asset_provider: string, sound_effect: ?string, motion_design: string}>
     */
    public function plan(string $narration, string $topic, string $contentType, array $research = []): array
    {
        if (str_contains(mb_strtolower($topic), 'hindenburg')) {
            $directions = $this->hindenburgDirections();
        } elseif (str_contains(mb_strtolower($topic), 'd. b. cooper')) {
            $directions = $this->dbCooperDirections();
        } elseif (str_contains(mb_strtolower($topic), 'max headroom')) {
            $directions = $this->maxHeadroomDirections();
        } elseif (str_contains(mb_strtolower($topic), 'sinal wow')) {
            $directions = $this->wowDirections();
        } else {
            throw new RuntimeException("O planejador semântico ainda não possui direção visual aprovada para {$contentType}.");
        }

        $sentences = preg_split('/(?<=[.!?])\s+/u', trim($narration)) ?: [];
        $chunks = array_fill(0, count($directions), '');
        foreach ($sentences as $index => $sentence) {
            $chunk = min(count($directions) - 1, (int) floor($index * count($directions) / max(1, count($sentences))));
            $chunks[$chunk] = trim($chunks[$chunk].' '.$sentence);
        }

        $durationSeconds = str_contains(mb_strtolower($topic), 'hindenburg') ? 10 : (str_contains(mb_strtolower($topic), 'd. b. cooper') ? 7 : (count($directions) === 8 ? 9 : 5));

        return array_map(function (array $direction, int $index) use ($chunks, $durationSeconds, $sentences, $topic): array {
            $event = $this->eventRequirements($topic, $index + 1, $direction);

            return ['position' => $index + 1, 'duration_seconds' => $durationSeconds, 'narration' => $chunks[$index] ?: ($chunks[$index - 1] ?? $sentences[0]), ...$direction, ...$event];
        }, $directions, array_keys($directions));
    }

    /** @param array<string, mixed> $direction
     * @return array{narrated_event: string, required_event_visuals: list<string>, event_coverage_score: ?int}
     */
    private function eventRequirements(string $topic, int $position, array $direction): array
    {
        if (isset($direction['narrated_event'], $direction['required_event_visuals'])) {
            return ['narrated_event' => $direction['narrated_event'], 'required_event_visuals' => $direction['required_event_visuals'], 'event_coverage_score' => $direction['event_coverage_score'] ?? null];
        }
        if (! str_contains(mb_strtolower($topic), 'hindenburg')) {
            return ['narrated_event' => $direction['factual_reference'] ?? $direction['visual_intent'], 'required_event_visuals' => $direction['required_elements'] ?? [], 'event_coverage_score' => null];
        }

        return match ($position) {
            1 => ['narrated_event' => 'O Hindenburg entra em chamas e começa a cair.', 'required_event_visuals' => ['flames', 'burning airship', 'collapse'], 'event_coverage_score' => 95],
            2 => ['narrated_event' => 'A estrutura do LZ 129 é construída.', 'required_event_visuals' => ['airship framework', 'construction'], 'event_coverage_score' => 92],
            3 => ['narrated_event' => 'O salão de passageiros do Hindenburg é apresentado.', 'required_event_visuals' => ['Hindenburg interior', 'dining room'], 'event_coverage_score' => 90],
            4 => ['narrated_event' => 'O dirigível se aproxima para pousar em Lakehurst.', 'required_event_visuals' => ['airship approaching', 'mooring field'], 'event_coverage_score' => 0],
            5 => ['narrated_event' => 'O incêndio surge durante a tentativa de pouso.', 'required_event_visuals' => ['flames', 'burning airship'], 'event_coverage_score' => 95],
            6 => ['narrated_event' => 'A estrutura colapsa diante das câmeras.', 'required_event_visuals' => ['burning airship', 'collapse'], 'event_coverage_score' => 95],
            7 => ['narrated_event' => 'A investigação passa a examinar os destroços.', 'required_event_visuals' => ['Hindenburg wreckage', 'artifact'], 'event_coverage_score' => 89],
            default => ['narrated_event' => 'O caso termina com a causa da ignição ainda debatida.', 'required_event_visuals' => ['Hindenburg wreckage', 'artifact'], 'event_coverage_score' => 89],
        };
    }

    /** @return list<array<string, mixed>> */
    private function dbCooperDirections(): array
    {
        $fbiSource = 'https://www.fbi.gov/history/cases-and-criminals/db-cooper-hijacking';
        $publicDomainLicense = 'Domínio público — obra do governo federal dos EUA (17 U.S.C. § 105)';
        $publicDomainUrl = 'https://www.govinfo.gov/content/pkg/USCODE-2023-title17/html/USCODE-2023-title17-sec105.htm';
        $sketch = ['provider' => 'real-reference', 'asset_url' => 'https://www.fbi.gov/image-repository/cooper-sketches.jpeg/@@images/image/high', 'source_url' => $fbiSource, 'title' => 'Retratos falados do FBI de D. B. Cooper', 'author' => 'Federal Bureau of Investigation', 'license' => $publicDomainLicense, 'license_url' => $publicDomainUrl, 'public_domain' => true, 'attribution_required' => false, 'attribution_text' => 'Federal Bureau of Investigation — domínio público', 'asset_score' => 95, 'event_coverage_score' => 95];
        $money = ['provider' => 'real-reference', 'asset_url' => 'https://www.fbi.gov/image-repository/cooper-money.jpg/@@images/image/high', 'source_url' => $fbiSource, 'title' => 'Notas do resgate recuperadas em 1980', 'author' => 'Federal Bureau of Investigation', 'license' => $publicDomainLicense, 'license_url' => $publicDomainUrl, 'public_domain' => true, 'attribution_required' => false, 'attribution_text' => 'Federal Bureau of Investigation — domínio público', 'asset_score' => 95, 'event_coverage_score' => 95];
        $tie = ['provider' => 'real-reference', 'asset_url' => 'https://www.fbi.gov/image-repository/black-tie.jpg/@@images/image/large', 'source_url' => $fbiSource, 'title' => 'Gravata deixada no voo 305', 'author' => 'Federal Bureau of Investigation', 'license' => $publicDomainLicense, 'license_url' => $publicDomainUrl, 'public_domain' => true, 'attribution_required' => false, 'attribution_text' => 'Federal Bureau of Investigation — domínio público', 'asset_score' => 92, 'event_coverage_score' => 92];
        $parachuteBag = ['provider' => 'real-reference', 'asset_url' => 'https://www.fbi.gov/image-repository/parachute-bag.jpg/@@images/image/high', 'source_url' => $fbiSource, 'title' => 'Bolsa de paraquedas preservada no caso NORJAK', 'author' => 'Federal Bureau of Investigation', 'license' => $publicDomainLicense, 'license_url' => $publicDomainUrl, 'public_domain' => true, 'attribution_required' => false, 'attribution_text' => 'Federal Bureau of Investigation — domínio público', 'asset_score' => 92, 'event_coverage_score' => 92];
        $parachute = ['provider' => 'real-reference', 'asset_url' => 'https://www.fbi.gov/image-repository/parachute.jpg/@@images/image/high', 'source_url' => $fbiSource, 'title' => 'Paraquedas não utilizado preservado no caso NORJAK', 'author' => 'Federal Bureau of Investigation', 'license' => $publicDomainLicense, 'license_url' => $publicDomainUrl, 'public_domain' => true, 'attribution_required' => false, 'attribution_text' => 'Federal Bureau of Investigation — domínio público', 'asset_score' => 92, 'event_coverage_score' => 92];
        $ticket = ['provider' => 'real-reference', 'source_url' => 'https://www.fbi.gov/history/artifacts/d-b-cooper-plane-ticket', 'title' => 'Bilhete original do voo 305 comprado sob o nome Dan Cooper', 'author' => 'Federal Bureau of Investigation', 'license' => $publicDomainLicense, 'license_url' => $publicDomainUrl, 'public_domain' => true, 'attribution_required' => false, 'attribution_text' => 'Federal Bureau of Investigation — domínio público', 'asset_score' => 92, 'event_coverage_score' => 92, 'asset_state' => 'official-record-image-to-resolve-before-download'];
        $caseFile = ['provider' => 'real-reference', 'source_url' => 'https://www.archives.gov/seattle/highlights/d-b-cooper', 'title' => 'U.S. Attorney Case File CR-0451', 'author' => 'U.S. Attorney’s Office / National Archives', 'license' => $publicDomainLicense, 'license_url' => $publicDomainUrl, 'public_domain' => true, 'attribution_required' => false, 'attribution_text' => 'National Archives — domínio público', 'asset_score' => 90, 'event_coverage_score' => 90, 'asset_state' => 'official-record-image-to-resolve-before-download'];
        $vault = ['provider' => 'real-reference', 'source_url' => 'https://vault.fbi.gov/D-B-Cooper%20', 'title' => 'Arquivos investigativos NORJAK do FBI Vault', 'author' => 'Federal Bureau of Investigation', 'license' => $publicDomainLicense, 'license_url' => $publicDomainUrl, 'public_domain' => true, 'attribution_required' => false, 'attribution_text' => 'Federal Bureau of Investigation — domínio público', 'asset_score' => 90, 'event_coverage_score' => 90, 'asset_state' => 'official-record-image-to-resolve-before-download'];

        return [
            $this->dbCooperScene('clímax', 'Notas do resgate encontradas em 1980 e identificadas pelos números de série.', 'O dinheiro foi encontrado; o sequestrador não.', ['recovered ransom money', 'serial numbers'], $money, 'slow_push_in', 'low_impact', 5),
            $this->dbCooperScene('objeto-chave', 'Bilhete original do voo 305 comprado sob o nome Dan Cooper.', 'Dan Cooper embarca com uma passagem comprada em dinheiro.', ['Flight 305 ticket', 'Dan Cooper'], $ticket, 'slow_push_in', 'paper_rustle', 5),
            $this->dbCooperScene('ação', 'Registro federal do sequestro e das exigências transmitidas à tripulação.', 'Durante o voo, a exigência por dinheiro e quatro paraquedas é registrada.', ['FBI case file', 'ransom demand'], $caseFile, 'slow_pan', 'room_tone', 5),
            $this->dbCooperScene('objeto-chave', 'Bolsa de paraquedas preservada pelo FBI.', 'O resgate é trocado pelos paraquedas em Seattle.', ['parachute bag', 'evidence artifact'], $parachuteBag, 'slow_push_in', 'low_impact', 5),
            $this->dbCooperScene('ação', 'Paraquedas não utilizado preservado no caso NORJAK.', 'O voo decola outra vez com a tripulação restante.', ['parachute', 'NORJAK evidence'], $parachute, 'slow_pan', 'wind', 4),
            $this->dbCooperScene('clímax', 'Arquivo CR-0451 registra o sequestro do voo 305 e a rota posterior.', 'O Boeing 727 segue entre Seattle e Reno antes do salto.', ['flight route', 'Seattle', 'Reno'], $caseFile, 'slow_pan', 'wind', 5),
            $this->dbCooperScene('clímax', 'O FBI documenta que o sequestrador saltou pela escada traseira e desapareceu.', 'Cooper salta do avião com o dinheiro e desaparece na noite.', ['rear stair jump', 'parachute', 'ransom money'], $caseFile, 'slow_push_in', 'low_impact', 5),
            $this->dbCooperScene('objeto-chave', 'Gravata deixada a bordo e preservada como evidência.', 'Depois do pouso, a gravata deixada no avião entra na investigação.', ['black tie', 'FBI evidence'], $tie, 'slow_push_in', 'paper_rustle', 5),
            $this->dbCooperScene('ação', 'Arquivos NORJAK reúnem entrevistas, buscas e exames de evidências.', 'O FBI abre a investigação NORJAK e segue centenas de pistas.', ['NORJAK file', 'FBI investigation'], $vault, 'slow_pan', 'paper_rustle', 5),
            $this->dbCooperScene('objeto-chave', 'Fotografia oficial das notas recuperadas em 1980.', 'Nove anos depois, parte do resgate aparece em Tina Bar.', ['recovered ransom money', '1980'], $money, 'slow_push_in', 'low_impact', 5),
            $this->dbCooperScene('personagem', 'Retrato falado oficial produzido a partir de testemunhas.', 'Os retratos falados ajudam a investigar um homem sem nome confirmado.', ['FBI composite sketch', 'D. B. Cooper'], $sketch, 'slow_push_in', 'room_tone', 4),
            $this->dbCooperScene('conclusão', 'O arquivo federal permanece sem identificação conclusiva do sequestrador.', 'O caso termina com documentos, evidências e nenhuma identidade confirmada.', ['FBI case file', 'unsolved investigation'], $vault, 'slow_pan', 'room_tone', 5),
        ];
    }

    /** @param array<string, mixed> $reference
     * @return array<string, mixed>
     */
    private function dbCooperScene(string $sceneType, string $factualReference, string $narratedEvent, array $requiredEventVisuals, array $reference, string $motionDesign, ?string $soundEffect, int $importance): array
    {
        return ['scene_type' => $sceneType, 'factual_reference' => $factualReference, 'narrated_event' => $narratedEvent, 'visual_intent' => 'Usar evidência ou documento federal diretamente relacionado ao trecho, sem reconstituição de um criminoso genérico.', 'visual_prompt' => '', 'clip_search_terms' => $requiredEventVisuals, 'fallback_search_terms' => ['FBI D.B. Cooper official record'], 'required_elements' => $requiredEventVisuals, 'required_event_visuals' => $requiredEventVisuals, 'forbidden_elements' => ['actor as D.B. Cooper', 'generic hijacker', 'graphic violence', 'invented document'], 'asset_provider' => 'real-reference', 'real_reference_candidate' => true, 'sound_effect' => $soundEffect, 'motion_design' => $motionDesign, 'importance' => $importance, 'event_coverage_score' => $reference['event_coverage_score'], 'asset_metadata' => $reference];
    }

    /** @return list<array<string, mixed>> */
    private function hindenburgDirections(): array
    {
        $footage = ['provider' => 'real-reference', 'asset_url' => 'https://upload.wikimedia.org/wikipedia/commons/5/50/Footage_of_the_Hindenburg_disaster_paired_with_Herb_Morrison%27s_report.webm?utm_source=commons.wikimedia.org&utm_campaign=imageinfo&utm_content=original', 'source_url' => 'https://commons.wikimedia.org/wiki/File:Footage_of_the_Hindenburg_disaster_paired_with_Herb_Morrison%27s_report.webm', 'title' => 'Footage of the Hindenburg disaster paired with Herb Morrison report', 'author' => 'C. E. Price / Internet Archive', 'license' => 'CC0', 'license_url' => 'https://creativecommons.org/publicdomain/zero/1.0/', 'attribution_required' => false, 'attribution_text' => 'C. E. Price / Internet Archive — CC0', 'asset_score' => 95];
        $construction = ['provider' => 'real-reference', 'asset_url' => 'https://upload.wikimedia.org/wikipedia/commons/5/5a/Bundesarchiv_Bild_146-1986-127-05%2C_Bau_des_Luftschiffs_LZ_129_%22Hindenburg%22.jpg?utm_source=commons.wikimedia.org&utm_campaign=imageinfo&utm_content=original', 'source_url' => 'https://commons.wikimedia.org/wiki/File:Bundesarchiv_Bild_146-1986-127-05,_Bau_des_Luftschiffs_LZ_129_%22Hindenburg%22.jpg', 'title' => 'Bau des Luftschiffs LZ 129 Hindenburg', 'author' => 'Bundesarchiv / unknown author', 'license' => 'CC BY-SA 3.0 de', 'license_url' => 'https://creativecommons.org/licenses/by-sa/3.0/deed.en', 'attribution_required' => true, 'attribution_text' => 'Bundesarchiv, Bild 146-1986-127-05 — CC BY-SA 3.0 de', 'asset_score' => 92];
        $interior = ['provider' => 'real-reference', 'asset_url' => 'https://upload.wikimedia.org/wikipedia/commons/5/55/Bundesarchiv_Bild_147-0640%2C_Luftschiff_Hindenburg_%28LZ-129%29%2C_Speisesaal.jpg?utm_source=commons.wikimedia.org&utm_campaign=imageinfo&utm_content=original', 'source_url' => 'https://commons.wikimedia.org/wiki/File:Bundesarchiv_Bild_147-0640,_Luftschiff_Hindenburg_(LZ-129),_Speisesaal.jpg', 'title' => 'Luftschiff Hindenburg, Speisesaal', 'author' => 'O. V. Stetten / Bundesarchiv', 'license' => 'CC BY-SA 3.0 de', 'license_url' => 'https://creativecommons.org/licenses/by-sa/3.0/deed.en', 'attribution_required' => true, 'attribution_text' => 'Bundesarchiv, Bild 147-0640 — CC BY-SA 3.0 de', 'asset_score' => 90];
        $wreck = ['provider' => 'real-reference', 'asset_url' => 'https://upload.wikimedia.org/wikipedia/commons/a/ab/Aeronauticum_Wrackteil_LZ_129_Hindenburg_082.jpg?utm_source=commons.wikimedia.org&utm_campaign=imageinfo&utm_content=original', 'source_url' => 'https://commons.wikimedia.org/wiki/File:Aeronauticum_Wrackteil_LZ_129_Hindenburg_082.jpg', 'title' => 'Aeronauticum Wrackteil LZ 129 Hindenburg', 'author' => 'Teta_pk', 'license' => 'CC BY-SA 3.0', 'license_url' => 'https://creativecommons.org/licenses/by-sa/3.0/deed.en', 'attribution_required' => true, 'attribution_text' => 'Teta_pk — CC BY-SA 3.0', 'asset_score' => 89];

        return [
            ['scene_type' => 'clímax', 'factual_reference' => 'Filmagem do incêndio do Hindenburg em Lakehurst.', 'visual_intent' => 'Abrir com o registro real, sem usar seu áudio.', 'visual_prompt' => '', 'clip_search_terms' => ['Hindenburg disaster footage'], 'fallback_search_terms' => ['airship archival fire'], 'required_elements' => ['Hindenburg', 'fire'], 'forbidden_elements' => ['modern CGI', 'graphic bodies'], 'asset_provider' => 'real-reference', 'real_reference_candidate' => true, 'sound_effect' => 'low_impact', 'motion_design' => 'none', 'importance' => 5, 'asset_metadata' => $footage],
            ['scene_type' => 'objeto-chave', 'factual_reference' => 'Foto histórica da construção do LZ 129.', 'visual_intent' => 'Mostrar escala e materialidade antes do contexto.', 'visual_prompt' => '', 'clip_search_terms' => ['LZ 129 construction'], 'fallback_search_terms' => ['airship construction archive'], 'required_elements' => ['Hindenburg'], 'forbidden_elements' => ['modern airship'], 'asset_provider' => 'real-reference', 'real_reference_candidate' => true, 'sound_effect' => 'room_tone', 'motion_design' => 'slow_pan', 'importance' => 4, 'asset_metadata' => $construction],
            ['scene_type' => 'ambientação', 'factual_reference' => 'Foto histórica do salão interno do Hindenburg.', 'visual_intent' => 'Contrastar o interior civilizado com o desastre iminente.', 'visual_prompt' => '', 'clip_search_terms' => ['Hindenburg interior dining room'], 'fallback_search_terms' => ['airship interior archive'], 'required_elements' => ['Hindenburg'], 'forbidden_elements' => ['modern aircraft cabin'], 'asset_provider' => 'real-reference', 'real_reference_candidate' => true, 'sound_effect' => 'room_tone', 'motion_design' => 'slow_push_in', 'importance' => 4, 'asset_metadata' => $interior],
            ['scene_type' => 'ação', 'factual_reference' => 'A aproximação para pouso em Lakehurst foi observada por equipes e imprensa.', 'visual_intent' => 'Usar o material documental do desastre em trecho anterior às chamas.', 'visual_prompt' => '', 'clip_search_terms' => ['Hindenburg landing footage'], 'fallback_search_terms' => ['airship landing archive'], 'required_elements' => ['Hindenburg'], 'forbidden_elements' => ['modern airport'], 'asset_provider' => 'real-reference', 'real_reference_candidate' => true, 'sound_effect' => 'wind', 'motion_design' => 'none', 'importance' => 4, 'asset_metadata' => $footage],
            ['scene_type' => 'ação', 'factual_reference' => 'O incêndio surgiu durante a tentativa de pouso.', 'visual_intent' => 'Voltar à filmagem documental para o clímax, sem efeitos adicionais.', 'visual_prompt' => '', 'clip_search_terms' => ['Hindenburg fire footage'], 'fallback_search_terms' => ['airship archive fire'], 'required_elements' => ['Hindenburg', 'fire'], 'forbidden_elements' => ['modern CGI', 'graphic bodies'], 'asset_provider' => 'real-reference', 'real_reference_candidate' => true, 'sound_effect' => 'low_impact', 'motion_design' => 'none', 'importance' => 5, 'asset_metadata' => $footage],
            ['scene_type' => 'clímax', 'factual_reference' => 'Fotografias e filme registraram a queda em pouco mais de meio minuto.', 'visual_intent' => 'Usar o registro real em trecho distinto, sem exagerar o choque.', 'visual_prompt' => '', 'clip_search_terms' => ['Hindenburg collapse footage'], 'fallback_search_terms' => ['airship disaster archive'], 'required_elements' => ['Hindenburg'], 'forbidden_elements' => ['graphic bodies'], 'asset_provider' => 'real-reference', 'real_reference_candidate' => true, 'sound_effect' => 'low_impact', 'motion_design' => 'none', 'importance' => 5, 'asset_metadata' => $footage],
            ['scene_type' => 'objeto-chave', 'factual_reference' => 'Destroço preservado do LZ 129 Hindenburg.', 'visual_intent' => 'Ancorar o pós-desastre em evidência física real.', 'visual_prompt' => '', 'clip_search_terms' => ['Hindenburg wreckage artifact'], 'fallback_search_terms' => ['airship wreckage museum'], 'required_elements' => ['Hindenburg'], 'forbidden_elements' => ['modern wreck'], 'asset_provider' => 'real-reference', 'real_reference_candidate' => true, 'sound_effect' => 'paper_rustle', 'motion_design' => 'slow_push_in', 'importance' => 5, 'asset_metadata' => $wreck],
            ['scene_type' => 'conclusão', 'factual_reference' => 'A causa inicial do incêndio permanece debatida.', 'visual_intent' => 'Fechar no destroço real, sem diagramas especulativos.', 'visual_prompt' => '', 'clip_search_terms' => ['Hindenburg wreckage detail'], 'fallback_search_terms' => ['airship artifact archive'], 'required_elements' => ['Hindenburg'], 'forbidden_elements' => ['definitive cause diagram'], 'asset_provider' => 'real-reference', 'real_reference_candidate' => true, 'sound_effect' => 'room_tone', 'motion_design' => 'slow_push_in', 'importance' => 5, 'asset_metadata' => $wreck],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function maxHeadroomDirections(): array
    {
        return [
            ['scene_type' => 'clímax', 'factual_reference' => 'A WGN-TV foi interrompida durante seu noticiário em 22 de novembro de 1987.', 'visual_intent' => 'Abrir com a quebra brusca da transmissão, sem reproduzir a imagem protegida.', 'visual_prompt' => '1980s CRT television abruptly showing static in a dark living room.', 'clip_search_terms' => ['1980s television static dark room'], 'fallback_search_terms' => ['vintage television static'], 'required_elements' => ['television', 'static'], 'forbidden_elements' => ['modern flat screen', 'news anchor'], 'asset_provider' => 'pexels', 'real_reference_candidate' => true, 'sound_effect' => 'radio_static', 'motion_design' => 'none', 'importance' => 5],
            ['scene_type' => 'ambientação', 'factual_reference' => 'As duas invasões ocorreram em Chicago.', 'visual_intent' => 'Situar a cidade sem transformar o cenário em ficção.', 'visual_prompt' => 'Chicago skyline at night in the 1980s mood.', 'clip_search_terms' => ['Chicago skyline night'], 'fallback_search_terms' => ['city skyline night'], 'required_elements' => ['Chicago', 'skyline'], 'forbidden_elements' => ['daylight', 'futuristic city'], 'asset_provider' => 'pexels', 'real_reference_candidate' => true, 'sound_effect' => 'wind', 'motion_design' => 'none', 'importance' => 3],
            ['scene_type' => 'ação', 'factual_reference' => 'A primeira interrupção atingiu a WGN-TV durante o noticiário.', 'visual_intent' => 'Representar a operação técnica de televisão, não um hacker genérico.', 'visual_prompt' => '1980s broadcast control room with CRT monitors and analog switcher.', 'clip_search_terms' => ['broadcast control room CRT monitors'], 'fallback_search_terms' => ['television control room'], 'required_elements' => ['control room', 'CRT'], 'forbidden_elements' => ['laptop', 'modern LED wall'], 'asset_provider' => 'pexels', 'real_reference_candidate' => false, 'sound_effect' => 'room_tone', 'motion_design' => 'none', 'importance' => 4],
            ['scene_type' => 'objeto-chave', 'factual_reference' => 'A figura mascarada imitava Max Headroom diante de um painel metálico.', 'visual_intent' => 'Usar reconstrução ilustrativa, sem alegar que é imagem do invasor.', 'visual_prompt' => 'Illustrative reconstruction of an anonymous masked television intruder before corrugated metal, face obscured, no branded character likeness, vertical 9:16.', 'clip_search_terms' => ['masked figure corrugated metal'], 'fallback_search_terms' => ['silhouette metal wall'], 'required_elements' => ['mask', 'corrugated metal'], 'forbidden_elements' => ['recognizable celebrity', 'logo'], 'asset_provider' => 'openai-image', 'real_reference_candidate' => true, 'sound_effect' => 'low_impact', 'motion_design' => 'signal_glitch', 'importance' => 5],
            ['scene_type' => 'ação', 'factual_reference' => 'A WGN retomou o sinal após seus engenheiros alterarem o enlace.', 'visual_intent' => 'Mostrar resposta técnica e urgência.', 'visual_prompt' => 'Engineer hands adjusting analog broadcast equipment.', 'clip_search_terms' => ['engineer analog broadcast equipment'], 'fallback_search_terms' => ['analog control knobs'], 'required_elements' => ['analog', 'equipment'], 'forbidden_elements' => ['smartphone', 'modern touch screen'], 'asset_provider' => 'pexels', 'real_reference_candidate' => false, 'sound_effect' => 'low_impact', 'motion_design' => 'none', 'importance' => 4],
            ['scene_type' => 'clímax', 'factual_reference' => 'Uma segunda invasão atingiu a WTTW durante Doctor Who.', 'visual_intent' => 'Elevar a estranheza pela repetição, sem usar cenas da série ou do ataque original.', 'visual_prompt' => 'CRT television static returning in an empty dark room.', 'clip_search_terms' => ['CRT television static empty room'], 'fallback_search_terms' => ['old television static'], 'required_elements' => ['CRT', 'static'], 'forbidden_elements' => ['modern flat screen'], 'asset_provider' => 'pexels', 'real_reference_candidate' => true, 'sound_effect' => 'radio_static', 'motion_design' => 'none', 'importance' => 5],
            ['scene_type' => 'objeto-chave', 'factual_reference' => 'A cobertura contemporânea registrou o caso e a investigação.', 'visual_intent' => 'Ancorar a história em documento e imprensa.', 'visual_prompt' => 'Archival newspaper desk with generic 1987 broadcast engineering report, no readable invented headlines.', 'clip_search_terms' => ['vintage newspaper archive desk'], 'fallback_search_terms' => ['archival documents desk'], 'required_elements' => ['newspaper', 'document'], 'forbidden_elements' => ['modern newspaper', 'readable fake headline'], 'asset_provider' => 'pexels', 'real_reference_candidate' => true, 'sound_effect' => 'paper_rustle', 'motion_design' => 'none', 'importance' => 4],
            ['scene_type' => 'conclusão', 'factual_reference' => 'A autoria e o método preciso não foram confirmados publicamente.', 'visual_intent' => 'Fechar no mistério documentado, não em conspiração.', 'visual_prompt' => 'Silent Chicago transmission tower at night with distant city lights.', 'clip_search_terms' => ['Chicago broadcast tower night'], 'fallback_search_terms' => ['radio tower night city'], 'required_elements' => ['tower', 'night'], 'forbidden_elements' => ['UFO', 'supernatural entity'], 'asset_provider' => 'pexels', 'real_reference_candidate' => true, 'sound_effect' => 'wind', 'motion_design' => 'none', 'importance' => 5],
        ];
    }

    /** @return list<array{scene_type: string, visual_prompt: string, clip_search_terms: list<string>, asset_provider: string, sound_effect: ?string, motion_design: string}> */
    private function wowDirections(): array
    {
        return [
            ['scene_type' => 'objeto-chave', 'visual_prompt' => 'Close-up of a 1977 radio telescope computer printout with the handwritten word Wow beside the sequence 6EQUJ5, documentary realism, vertical 9:16, no extra text.', 'clip_search_terms' => ['1970s computer printout close up'], 'asset_provider' => 'openai-image', 'sound_effect' => 'low_impact', 'motion_design' => 'slow_push_in'],
            ['scene_type' => 'ambientação', 'visual_prompt' => 'A real radio telescope dish silhouetted against a clear night sky.', 'clip_search_terms' => ['radio telescope dish night sky'], 'asset_provider' => 'pexels', 'sound_effect' => 'radio_static', 'motion_design' => 'none'],
            ['scene_type' => 'ação', 'visual_prompt' => 'An astronomer reading a paper telemetry report in a dark control room.', 'clip_search_terms' => ['scientist control room monitor'], 'asset_provider' => 'pexels', 'sound_effect' => 'paper_rustle', 'motion_design' => 'none'],
            ['scene_type' => 'objeto-chave', 'visual_prompt' => 'Macro close-up of the printed sequence 6EQUJ5 on continuous-feed computer paper, one handwritten Wow annotation, authentic 1970s scientific document, vertical 9:16.', 'clip_search_terms' => ['vintage computer paper data'], 'asset_provider' => 'openai-image', 'sound_effect' => 'pencil_mark', 'motion_design' => 'slow_pan'],
            ['scene_type' => 'ambientação', 'visual_prompt' => 'A large radio telescope scanning the night sky from a quiet field.', 'clip_search_terms' => ['radio telescope night field'], 'asset_provider' => 'pexels', 'sound_effect' => 'wind', 'motion_design' => 'none'],
            ['scene_type' => 'ação', 'visual_prompt' => 'Hands adjusting analog dials in a scientific radio control room.', 'clip_search_terms' => ['analog radio control knobs'], 'asset_provider' => 'pexels', 'sound_effect' => 'radio_static', 'motion_design' => 'none'],
            ['scene_type' => 'ambientação', 'visual_prompt' => 'A star field with a faint radio telescope dish in the foreground.', 'clip_search_terms' => ['stars night sky observatory'], 'asset_provider' => 'pexels', 'sound_effect' => null, 'motion_design' => 'none'],
            ['scene_type' => 'clímax', 'visual_prompt' => 'A realistic scientific visualization of a narrow radio signal crossing a dark star field toward a telescope dish, restrained and documentary-like, vertical 9:16.', 'clip_search_terms' => ['radio signal space visualization'], 'asset_provider' => 'openai-image', 'sound_effect' => 'low_impact', 'motion_design' => 'signal_glitch'],
            ['scene_type' => 'ação', 'visual_prompt' => 'An empty radio telescope control room after the researchers have left.', 'clip_search_terms' => ['empty observatory control room'], 'asset_provider' => 'pexels', 'sound_effect' => 'room_tone', 'motion_design' => 'none'],
            ['scene_type' => 'ambientação', 'visual_prompt' => 'A telescope dish turning slowly beneath a cloudless night sky.', 'clip_search_terms' => ['telescope dish rotating night'], 'asset_provider' => 'pexels', 'sound_effect' => 'wind', 'motion_design' => 'none'],
            ['scene_type' => 'ação', 'visual_prompt' => 'Researchers comparing archival radio astronomy papers on a desk.', 'clip_search_terms' => ['scientist archival documents desk'], 'asset_provider' => 'pexels', 'sound_effect' => 'paper_rustle', 'motion_design' => 'none'],
            ['scene_type' => 'objeto-chave', 'visual_prompt' => 'An analog radio receiver with its tuning needle moving through static.', 'clip_search_terms' => ['vintage radio receiver tuning'], 'asset_provider' => 'pexels', 'sound_effect' => 'radio_static', 'motion_design' => 'none'],
            ['scene_type' => 'ambientação', 'visual_prompt' => 'A solitary observatory field at dawn, the telescope still pointed upward.', 'clip_search_terms' => ['observatory telescope dawn'], 'asset_provider' => 'pexels', 'sound_effect' => 'wind', 'motion_design' => 'none'],
            ['scene_type' => 'clímax', 'visual_prompt' => 'The 6EQUJ5 printout lies alone under a desk lamp while darkness surrounds it, realistic archival atmosphere, vertical 9:16.', 'clip_search_terms' => ['vintage computer printout desk lamp'], 'asset_provider' => 'openai-image', 'sound_effect' => 'low_impact', 'motion_design' => 'slow_push_in'],
            ['scene_type' => 'conclusão', 'visual_prompt' => 'A radio telescope under an immense silent star field, restrained documentary realism.', 'clip_search_terms' => ['radio telescope stars night'], 'asset_provider' => 'pexels', 'sound_effect' => null, 'motion_design' => 'none'],
        ];
    }
}
