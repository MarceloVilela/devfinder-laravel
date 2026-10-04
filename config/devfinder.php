<?php

declare(strict_types=1);

return [

    /*
    | Chaves obrigatórias: nome da variável de ambiente => chave de config que ela alimenta.
    | O `config:check` falha se alguma estiver vazia; o log de boot lista só os NOMES presentes.
    | `env()` só aparece em config/ (quebra com `config:cache`, que o Lambda usa).
    */
    'required' => env('APP_ENV') === 'production'
        // Lambda: a conexão vem inteira numa URL (pooled do Neon), lida do SSM pelo runtime do Bref.
        ? [
            'APP_KEY' => 'app.key',
            'DB_URL' => 'database.connections.pgsql.url',
            'CORS_ALLOWED_ORIGINS' => 'cors.allowed_origins',
            'JWT_SECRET' => 'devfinder.auth.jwt_secret',
            'GITHUB_CLIENT_ID' => 'devfinder.auth.github.client_id',
            'GITHUB_CLIENT_SECRET' => 'devfinder.auth.github.client_secret',
            'APP_WEB_URL' => 'devfinder.auth.web_url',
        ]
        : [
            'APP_KEY' => 'app.key',
            'DB_HOST' => 'database.connections.pgsql.host',
            'DB_DATABASE' => 'database.connections.pgsql.database',
            'DB_USERNAME' => 'database.connections.pgsql.username',
            'DB_PASSWORD' => 'database.connections.pgsql.password',
        ],

    'appname' => 'DevFinder',

    /*
    | Autenticação (Fase 4, ADR 0013). Segredos vêm do SSM em produção (`bref-ssm:`), nunca de arquivo versionado.
    */
    'auth' => [
        'jwt_secret' => env('JWT_SECRET'),
        'jwt_ttl_seconds' => 604800, // 7 dias, como o contrato (`bearerAuth`)
        'web_url' => rtrim((string) env('APP_WEB_URL', ''), '/'),
        'github' => [
            'client_id' => env('GITHUB_CLIENT_ID'),
            'client_secret' => env('GITHUB_CLIENT_SECRET'),
            // Opcional: sem ela o GitHub usa a URL de callback cadastrada no OAuth App (um App por ambiente).
            'redirect_uri' => env('GITHUB_REDIRECT_URI'),
            'timeout_seconds' => 5,
        ],
        // Sessão do navegador (F4-13): cookie httpOnly com o JWT, como o `devfinder-api` original. O Bearer segue valendo
        // (Swagger UI e chamadas servidor a servidor). `lax`: o navegador só chega à API pelo proxy do front (mesmo site).
        'session_cookie' => [
            'name' => 'devfinder_token',
            'path' => '/',
            'same_site' => 'lax',
            'secure' => env('APP_ENV') === 'production',
        ],
        'state_cookie' => [
            'name' => 'devfinder_oauth_state',
            'path' => '/v1/auth',
            'ttl_minutes' => 10,
            'secure' => env('APP_ENV') === 'production',
        ],
        'rate_limit_per_minute' => 10,
        'writes_per_minute' => 30,
    ],
];
