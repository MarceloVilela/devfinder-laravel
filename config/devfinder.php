<?php

declare(strict_types=1);

return [

    /*
    | Chaves obrigatórias: nome da variável de ambiente => chave de config que ela alimenta.
    | O `config:check` falha se alguma estiver vazia; o log de boot lista só os NOMES presentes.
    | `env()` só aparece em config/ (quebra com `config:cache`, que o Lambda usa).
    */
    'required' => [
        'APP_KEY' => 'app.key',
        'DB_HOST' => 'database.connections.pgsql.host',
        'DB_DATABASE' => 'database.connections.pgsql.database',
        'DB_USERNAME' => 'database.connections.pgsql.username',
        'DB_PASSWORD' => 'database.connections.pgsql.password',
    ],

    'appname' => 'DevFinder',
];
