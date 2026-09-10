<?php

namespace App\Services\Studio;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class TtsService
{
    public function __construct(private HttpFactory $http) {}

    /** @return array{path: string, model: string, voice: string} */
    public function synthesize(string $narration, string $directory): array
    {
        $apiKey = config('studio.openai.api_key');
        if (blank($apiKey)) {
            throw new RuntimeException('OPENAI_API_KEY não está configurada.');
        }
        $model = config('studio.openai.tts_model');
        $voice = config('studio.openai.tts_voice');
        $audio = $this->http->withToken($apiKey)->connectTimeout(10)->timeout(180)->post('https://api.openai.com/v1/audio/speech', ['model' => $model, 'voice' => $voice, 'input' => $narration, 'instructions' => 'Narre em português brasileiro com voz séria, natural e íntima. Comece com estranheza contida, acelere levemente nos primeiros segundos, use pausas curtas antes das revelações e termine em tom calmo, sem teatralidade.', 'speed' => 1.12, 'response_format' => 'mp3'])->throw()->body();
        $path = "{$directory}/narration.mp3";
        Storage::disk('local')->put($path, $audio);

        return compact('path', 'model', 'voice');
    }

    /** @return array{provider: string, configured: bool} */
    public function configuration(): array
    {
        return ['provider' => 'openai', 'configured' => filled(config('studio.openai.api_key'))];
    }
}
