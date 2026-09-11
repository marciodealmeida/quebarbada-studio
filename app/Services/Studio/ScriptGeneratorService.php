<?php

namespace App\Services\Studio;

use Illuminate\Http\Client\Factory as HttpFactory;
use RuntimeException;

class ScriptGeneratorService
{
    public function __construct(private HttpFactory $http) {}

    /**
     * @param  array{topic: string, content_type: string, editorial_note: string}  $topic
     * @return array{script: string, narration: string}
     */
    public function generate(array $topic, array $research = [], bool $dryRun = false): array
    {
        if (! $dryRun) {
            return $this->generateWithOpenAi($topic, $research);
        }

        return $this->fallback($topic, $research);
    }

    /**
     * @param  array{topic: string, content_type: string, editorial_note: string}  $topic
     * @return array{script: string, narration: string}
     */
    private function generateWithOpenAi(array $topic, array $research, bool $isRetry = false): array
    {
        $apiKey = config('studio.openai.api_key');
        if (blank($apiKey)) {
            throw new RuntimeException('OPENAI_API_KEY não está configurada.');
        }

        $schema = [
            'type' => 'object',
            'properties' => ['title' => ['type' => 'string'], 'narration' => ['type' => 'string']],
            'required' => ['title', 'narration'],
            'additionalProperties' => false,
        ];
        $prompt = <<<PROMPT
Escreva em português brasileiro uma narração de 170 a 190 palavras para um vídeo vertical de suspense sobre "{$topic['topic']}".
É um(a) {$topic['content_type']}. Diretriz factual: {$topic['editorial_note']}
Use somente fatos deste dossiê: {$research['summary']}. Fatos principais: {$this->factsForPrompt($research)}. Diferencie explicitamente hipótese e incerteza.
Abra com uma única frase de até 13 palavras, concreta, intrigante e de alto impacto. A frase precisa introduzir perigo, estranheza ou uma pergunta específica que impeça um swipe nos primeiros 3 segundos. Não comece com data, contexto histórico, "dizem", "há versões" ou uma pergunta genérica. Nos primeiros 15 segundos, entregue pelo menos três fatos novos em frases curtas. Construa tensão com detalhes verificáveis, diferencie fatos de hipóteses e não invente fontes, nomes, datas ou locais. O encerramento precisa ser uma frase final completa e conclusiva, sem chamada para ação. Não inclua instruções de produção.
PROMPT;
        if ($isRetry) {
            $prompt .= "\nA resposta anterior foi rejeitada por ser curta. Entregue entre 175 e 185 palavras sem perder o gancho e a conclusão.";
        }

        $response = $this->http->withToken($apiKey)->acceptJson()->connectTimeout(10)->timeout(120)
            ->post('https://api.openai.com/v1/responses', [
                'model' => config('studio.openai.text_model'),
                'input' => $prompt,
                'store' => false,
                'text' => ['format' => ['type' => 'json_schema', 'name' => 'studio_script', 'strict' => true, 'schema' => $schema]],
            ])->throw()->json();
        $outputText = data_get($response, 'output_text') ?? data_get($response, 'output.0.content.0.text');
        $content = json_decode((string) $outputText, true);

        if (! is_array($content) || blank($content['title'] ?? null) || blank($content['narration'] ?? null)) {
            throw new RuntimeException('A OpenAI não retornou um roteiro estruturado válido.');
        }

        $wordCount = count(preg_split('/\s+/', trim($content['narration'])) ?: []);
        if ($wordCount < 165) {
            if (! $isRetry) {
                return $this->generateWithOpenAi($topic, $research, true);
            }

            throw new RuntimeException('O roteiro regenerado não atingiu a densidade necessária e foi rejeitado.');
        }
        if ($wordCount > 195) {
            $words = preg_split('/\s+/', trim($content['narration'])) ?: [];
            $content['narration'] = implode(' ', array_slice($words, 0, 190)).'.';
        }

        if (! $this->hasRetentiveHook($content['narration'])) {
            if (! $isRetry) {
                return $this->generateWithOpenAi($topic, $research, true);
            }

            throw new RuntimeException('O gancho do roteiro não passou na validação de retenção dos primeiros 3 segundos.');
        }

        return ['script' => "Título: {$content['title']}\n\n{$content['narration']}", 'narration' => $content['narration']];
    }

    public function hasRetentiveHook(string $narration): bool
    {
        $opening = trim((string) (preg_split('/(?<=[.!?])\s+/u', $narration)[0] ?? ''));
        $wordCount = count(preg_split('/\s+/u', $opening) ?: []);
        $startsWithContext = preg_match('/^(em\s+\d{4}|em\s+\w+\s+de\s+\d{4}|a história|o caso|dizem|há versões|ninguém sabe)/iu', $opening);
        $hasImmediateTension = str_contains($opening, '?') || preg_match('/\b(cortaram|fugiram|desapareceu|desapareceram|invadiu|sumiu|morreu|ninguém|nunca|abriu|abertas)\b/iu', $opening);

        return $wordCount > 0 && $wordCount <= 13 && ! $startsWithContext && (bool) $hasImmediateTension;
    }

    /** @param array{topic: string, content_type: string, editorial_note: string} $topic
     * @return array{script: string, narration: string}
     */
    private function fallback(array $topic, array $research): array
    {
        if (str_contains(mb_strtolower($topic['topic']), 'd. b. cooper')) {
            $narration = 'O dinheiro foi encontrado; o homem, nunca. Em 24 de novembro de 1971, um homem que se apresentou como Dan Cooper comprou, em dinheiro, uma passagem de Portland para Seattle. No voo 305, entregou uma nota a uma comissária e disse ter uma bomba. A exigência era concreta: duzentos mil dólares em notas de vinte e quatro paraquedas. Em Seattle, trinta e seis passageiros saíram do avião. O dinheiro e os paraquedas entraram. A tripulação restante decolou outra vez, rumo ao sul. Pouco depois das oito da noite, em algum lugar entre Seattle e Reno, Cooper abriu a escada traseira de um Boeing 727 e saltou na escuridão. O avião pousou. Ele não estava mais lá. O FBI chamou o caso de NORJAK, entrevistou centenas de suspeitos e examinou o avião em busca de evidências. A gravata deixada a bordo ainda forneceu material para análises. Em 1980, um menino encontrou cinco mil e oitocentos dólares do resgate, reconhecidos pelos números de série. Isso prova que parte do dinheiro chegou ao solo. Não prova quem era Cooper, onde caiu nem se sobreviveu. Em 2016, o FBI redirecionou seus recursos. O sequestro terminou numa fuga registrada em documentos, mas sem um nome para o homem que saltou do voo 305.';

            return ['script' => "Título: {$topic['topic']}\n\n{$narration}", 'narration' => $narration];
        }
        if (str_contains(mb_strtolower($topic['topic']), 'hindenburg')) {
            $narration = 'As câmeras já estavam gravando quando o Hindenburg começou a pegar fogo. Em pouco mais de meio minuto, o maior dirigível do mundo caiu em Lakehurst, Nova Jersey. Era 6 de maio de 1937. A aeronave alemã se aproximava para pousar depois de cruzar o Atlântico. Havia fotógrafos, equipes de solo e repórteres esperando. Então, as chamas apareceram perto da parte traseira. O fogo correu pela estrutura e o dirigível desabou diante de todos. Em segundos, o símbolo da era aérea virou ruína. O desastre foi registrado em filme, fotografia e uma transmissão de rádio que se tornou histórica. As imagens mostram a construção gigantesca, o salão interno e, depois, os destroços. A investigação reuniu relatos, fotos e peças da aeronave, mas não encerrou a dúvida sobre o primeiro instante do fogo. O que iniciou o incêndio continua debatido. Há hipóteses técnicas sobre vazamento de hidrogênio, eletricidade estática e o revestimento da aeronave. Nenhuma deve ser tratada como certeza isolada. O Hindenburg não desapareceu num mistério: ele caiu diante das câmeras e mudou para sempre a confiança no futuro dos grandes dirigíveis.';

            return ['script' => "Título: {$topic['topic']}\n\n{$narration}", 'narration' => $narration];
        }
        if (str_contains(mb_strtolower($topic['topic']), 'max headroom')) {
            $narration = 'Alguém invadiu a TV ao vivo — e nunca foi encontrado. Em 22 de novembro de 1987, espectadores de Chicago viram o sinal da WGN-TV desaparecer durante o noticiário. No lugar, entrou uma figura mascarada, diante de um painel metálico, imitando Max Headroom. Os engenheiros reagiram e recuperaram a transmissão. Mais tarde, a mesma noite ficou pior. A WTTW, durante Doctor Who, também foi tomada por uma transmissão pirata. A figura voltou, falou frases confusas e o sinal caiu de novo. As emissoras e a FCC trataram o caso como uma invasão real de sinal. Há uma hipótese técnica: alguém pode ter sobreposto o enlace de micro-ondas das estações. Mas o método preciso nunca foi confirmado publicamente. Nem a identidade dos responsáveis. O que ficou foram registros, reportagens e uma pergunta sem resposta: quem conseguiu entrar na televisão de uma cidade inteira e desaparecer?';

            return ['script' => "Título: {$topic['topic']}\n\n{$narration}", 'narration' => $narration];
        }

        $narration = 'Em 1977, um radiotelescópio captou um sinal que durou apenas 72 segundos. Ele apareceu uma única vez e nunca mais foi ouvido. O astrônomo Jerry Ehman viu a sequência 6EQUJ5 impressa no relatório e escreveu ao lado: Wow. O registro veio do Big Ear, em Ohio, durante uma busca por emissões de rádio fora da Terra. Era estreito, intenso e tinha o comportamento esperado de uma fonte distante. Não havia uma gravação para ouvir depois: só a medição impressa em papel. Mas havia um problema: o telescópio não conseguia apontar exatamente de onde ele veio. Nos dias seguintes, a equipe voltou à mesma região do céu. Nada. Décadas depois, outros radiotelescópios também procuraram. Nada. Existem hipóteses: um fenômeno natural, interferência humana, até um cometa. Nenhuma resolveu todos os detalhes do registro. Cada nova explicação precisa responder por que ele foi tão intenso e por que nunca se repetiu. O sinal Wow não prova que alguém respondeu do outro lado. Mas prova que, por 72 segundos, algo no céu foi registrado — e deixou apenas uma palavra escrita como testemunha.';

        return ['script' => "Título: {$topic['topic']}\n\n{$narration}", 'narration' => $narration];
    }

    /** @param array<string, mixed> $research */
    private function factsForPrompt(array $research): string
    {
        return collect($research['key_facts'] ?? [])->map(fn (array $fact): string => "{$fact['status']}: {$fact['claim']}")->implode(' | ');
    }
}
