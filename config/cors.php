<?php

declare(strict_types=1);

/*
| CORS (D-4): origem explícita por variável de ambiente, nunca `*`, sem credenciais.
| `CORS_ALLOWED_ORIGINS` aceita várias origens separadas por vírgula; vazia = nenhuma origem liberada.
*/
return [
    'paths' => ['v1/*', 'v1'],
    'allowed_methods' => ['GET', 'POST', 'DELETE', 'OPTIONS'],
    'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))))),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Authorization', 'Content-Type', 'Accept', 'X-Request-Id'],
    'exposed_headers' => ['X-Request-Id'],
    'max_age' => 600,
    'supports_credentials' => false,
];
