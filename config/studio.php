<?php

return [
    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'text_model' => env('STUDIO_OPENAI_TEXT_MODEL', 'gpt-4.1-mini'),
        'tts_model' => env('STUDIO_OPENAI_TTS_MODEL', 'gpt-4o-mini-tts'),
        'tts_voice' => env('STUDIO_OPENAI_TTS_VOICE', 'onyx'),
    ],

    'pexels' => [
        'api_key' => env('PEXELS_API_KEY'),
    ],

    'default_channel' => env('STUDIO_DEFAULT_CHANNEL', 'que-sinistro'),
    'video' => [
        'min_seconds' => (int) env('STUDIO_VIDEO_MIN_SECONDS', 65),
        'target_seconds' => (int) env('STUDIO_VIDEO_TARGET_SECONDS', 72),
        'max_seconds' => (int) env('STUDIO_VIDEO_MAX_SECONDS', 80),
        'resolution' => '1080x1920',
    ],
    'max_cost_usd' => (float) env('STUDIO_MAX_COST_USD', 1.50),
    'estimated_costs' => ['script_usd' => 0.08, 'tts_usd' => 0.12, 'pexels_usd' => 0.00, 'image_usd' => 0.72, 'render_usd' => 0.00],
];
