<?php

declare(strict_types=1);

// O `<env force>` do phpunit.xml NÃO vence o `$_SERVER` que o docker injeta com `env_file: .env` (achado na Fase 5: localmente os
// testes rodavam no banco de desenvolvimento e o `RefreshDatabase` o apagava). Aqui o ambiente de teste é imposto antes de o app subir.
foreach ([
    'DB_DATABASE' => 'devfinder_test',
    'CACHE_STORE' => 'array',
    'CACHE_LIMITER_STORE' => 'array',
    'SESSION_DRIVER' => 'array',
    'QUEUE_CONNECTION' => 'sync',
] as $key => $value) {
    $_SERVER[$key] = $_ENV[$key] = $value;
    putenv("{$key}={$value}");
}

use Studio\Gesso\Laravel\ValidatesOpenApiSchema;
use Tests\TestCase;

uses(TestCase::class)->in('Feature');
uses(Illuminate\Foundation\Testing\RefreshDatabase::class)->in('Feature/Read', 'Contract/ReadRoutesContractTest.php');
uses(TestCase::class, ValidatesOpenApiSchema::class)->in('Contract');

/** Quantas queries a closure executa (orçamento por operação, Fase 3). */
function countQueries(Closure $callback): int
{
    $count = 0;
    Illuminate\Support\Facades\DB::listen(static function () use (&$count): void {
        $count++;
    });
    $callback();

    return $count;
}

/**
 * Cabeçalho `Authorization` com um token válido do dev (o dev precisa existir no banco).
 *
 * @return array<string, string>
 */
function as_dev(string $username): array
{
    return ['Authorization' => 'Bearer ' . app(App\Features\Auth\Support\TokenCodec::class)->issue($username)];
}

/** Perfil público do GitHub falso para `POST /devs` e `userGithub` (qualquer login responde com ele, salvo o 404). */
function fakeGithubUsers(string $notFound = ''): void
{
    Illuminate\Support\Facades\Http::fake([
        'api.github.com/users/*' => function (Illuminate\Http\Client\Request $request) use ($notFound) {
            $login = basename((string) parse_url($request->url(), PHP_URL_PATH));

            return $login === $notFound
                ? Illuminate\Support\Facades\Http::response(['message' => 'Not Found'], 404)
                : Illuminate\Support\Facades\Http::response(['login' => $login, 'name' => 'Nome ' . $login, 'bio' => null, 'avatar_url' => "https://avatars.example.test/{$login}.png"]);
        },
    ]);
}

/**
 * Como `as_dev`, depois de promover o dev a ADMIN direto no banco (é assim que o papel se define, F5-15).
 *
 * @return array<string, string>
 */
function as_admin(string $username): array
{
    Illuminate\Support\Facades\DB::table('devs')->where('username', $username)->update(['role' => 'ADMIN']);

    return as_dev($username);
}
