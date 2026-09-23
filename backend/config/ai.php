<?php

return [
    'provider' => env('AI_PROVIDER', 'auto'),
    'openai' => [
        'key' => env('OPENAI_API_KEY', ''),
        'model' => env('OPENAI_MODEL', 'gpt-5.4-mini'),
        'timeout' => 40,
        'max_output_tokens' => 2500,
    ],
    'requests_per_minute' => 6,
    'requests_per_day' => 100,
];
