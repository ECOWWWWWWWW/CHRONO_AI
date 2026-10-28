<?php
return [
    'default' => env('AI_DEFAULT_PROVIDER', 'gemini'),

'providers' => [
    'gemini' => [
        'driver' => 'gemini',
        'key' => env('GEMINI_API_KEY'),
    ],
],

];