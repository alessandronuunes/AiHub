<?php

return [
    'name' => 'AiHub',
    'ai_provider' => env('AI_PROVIDER', 'openai'),
    'providers' => [
        'openai_api_key' => env('OPENAI_API_KEY'),
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_MODEL'),
        ],
        // outros provedores
    ]
];
