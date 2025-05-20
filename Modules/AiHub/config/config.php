<?php

return [
    'name' => 'aihub',
    'ai_provider' => env('AI_PROVIDER', 'openai'),
    'providers' => [
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_MODEL'),
        ],
        // other providers
    ],
];
