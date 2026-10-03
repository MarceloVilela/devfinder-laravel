<?php

declare(strict_types=1);

return [
    /*
    | A rota `POST /signed-upload-url` do bref/laravel-bridge (URL assinada de upload para S3) fica DESLIGADA:
    | o domínio não recebe arquivos e não há bucket de aplicação (plan.md, "Sem S3 de aplicação").
    | `null` faz o ServiceProvider não registrá-la; o gate `gesso:routes --fail-on-undocumented` vigia.
    */
    'uploads' => [
        'route' => null,
    ],
];
