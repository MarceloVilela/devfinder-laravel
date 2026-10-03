<?php

declare(strict_types=1);

// Regras de `specs/arquitetura-alvo.md` (gate G4). Protegem a estrutura por feature (ADR 0005).

arch('todo arquivo de app declara strict_types')
    ->expect('App')
    ->toUseStrictTypes();

arch('controller não toca Eloquent, banco nem cliente HTTP')
    ->expect('App\Features\*\Http\Controllers')
    ->not->toUse([
        'Illuminate\Database',
        'Illuminate\Support\Facades\DB',
        'Illuminate\Support\Facades\Http',
        'Illuminate\Http\Client',
    ]);

// Regra "controller não importa Models da feature" entra com o primeiro Model (Fase 3): o Pest falha
// com DirectoryNotFound se o namespace não existe, então não dá para declará-la antes.

arch('Shared não importa feature')
    ->expect('App\Shared')
    ->not->toUse('App\Features');

arch('Queries e Actions não dependem de HTTP')
    ->expect(['App\Features\*\Queries', 'App\Features\*\Actions'])
    ->not->toUse(['App\Features\*\Http', 'Illuminate\Http\Request']);

arch('env() só em config/')
    ->expect('env')
    ->not->toBeUsedIn('App');

arch('sem dd, dump nem ray em código de produção')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->not->toBeUsedIn('App');

arch('exceções de feature estendem ApiException')
    ->expect('App\Features\*\Exceptions')
    ->toExtend('App\Shared\Exceptions\ApiException');

arch('controllers são final e invocáveis')
    ->expect('App\Features\*\Http\Controllers')
    ->toBeFinal()
    ->toHaveMethod('__invoke');
