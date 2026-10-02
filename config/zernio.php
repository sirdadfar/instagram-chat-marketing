<?php
return [
    'base_url' => env('ZERNIO_BASE_URL', 'https://zernio.com/api/v1'),
    'api_key' => env('ZERNIO_API_KEY'),
    'webhook_secret' => env('ZERNIO_WEBHOOK_SECRET'),
    'timeout' => 20,
];
