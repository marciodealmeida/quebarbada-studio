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
    public function generate(array $topic, bool $dryRun = false): array
    {
        if (! $dryRun) {
            return $this->generateWithOpenAi($topic);
        }

        $narration = "A história de {$topic['topic']} atravessa gerações em diferentes versões. Ela não é um caso comprovado: trata-se de uma {$topic['content_type']}, preservada pela memória popular. Segundo quem conta a lenda, há sempre um lugar, uma noite e um detalhe que muda a cada relato. Algumas versões falam de passos, outras de silêncio ou de uma presença percebida de longe. Não existe uma origem única confirmada. Esse é o traço comum do folclore: narrativas mudam de região para região e revelam medos coletivos. Elas permanecem porque aproximam o estranho de cenários familiares, como uma estrada vazia, uma mata escura ou uma casa silenciosa. Ainda assim, uma lenda não é prova de que algo sobrenatural tenha acontecido. O mistério está na forma como a história continua viva, mesmo quando ninguém consegue dizer onde ela começou. Talvez seja por isso que ela ainda provoca desconforto: cada pessoa imagina o que teria visto se estivesse ali.";

        return ['script' => "Título: {$topic['topic']}\n\n{$narration}", 'narration' => $narration];
    }

    /**
     * @param  array{topic: string, content_type: string, editorial_note: string}  $topic
     * @return array{script: string, narration: string}
     */
    private function generateWithOpenAi(array $topic): array
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
Escreva em português brasileiro uma narração de 165 a 180 palavras para um vídeo vertical de suspense sobre "{$topic['topic']}".
É uma {$topic['content_type']}. Nunca afirme fenômenos sobrenaturais, aparições ou relatos como fatos comprovados. Use formulações como "segundo versões populares", "a lenda diz" e "não há confirmação" quando necessário. Não invente nomes, datas, locais específicos ou fontes. O texto deve ter abertura forte, desenvolvimento e fechamento memorável, sem instruções de produção.
PROMPT;

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
        if ($wordCount < 155) {
            $content['narration'] .= ' Como toda lenda urbana, essa história muda de detalhes conforme quem a conta. O que permanece é o medo compartilhado, não uma prova de que algo sobrenatural tenha acontecido. Talvez seja por isso que ela continua viva em tantos corredores, mesmo quando as luzes se apagam.';
            $wordCount = count(preg_split('/\s+/', trim($content['narration'])) ?: []);
        }
        if ($wordCount > 180) {
            $words = preg_split('/\s+/', trim($content['narration'])) ?: [];
            $content['narration'] = implode(' ', array_slice($words, 0, 175));
        }

        return ['script' => "Título: {$content['title']}\n\n{$content['narration']}", 'narration' => $content['narration']];
    }
}
