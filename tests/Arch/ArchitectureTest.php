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

arch('controller não importa Models')
    ->expect('App\Features\*\Http\Controllers')
    ->not->toUse('App\Features\*\Models');

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

// "Uma feature usa outra só por Actions e Data" (arquitetura-alvo.md). Uma feature pode usar `Actions` e `Data` de outra
// (ex.: `Auth` e `Channel` chamam `Dev\Actions\EnsureDev`); `Queries`, `Models`, `Http`, `Support` e `Console` de outra não.
$features = ['Auth', 'Channel', 'Description', 'Dev', 'Info', 'Search', 'Video'];
$internals = ['Queries', 'Models', 'Http', 'Support', 'Console', 'Exceptions'];

foreach ($features as $feature) {
    $forbidden = [];

    foreach (array_filter($features, static fn(string $other): bool => $other !== $feature) as $other) {
        foreach ($internals as $internal) {
            $forbidden[] = "App\\Features\\{$other}\\{$internal}";
        }
    }

    arch("{$feature} só usa Actions e Data de outra feature")
        ->expect("App\\Features\\{$feature}")
        ->not->toUse($forbidden);
}

arch('o segredo do JWT e do GitHub só é lido pelas bordas')
    ->expect(['Firebase\JWT'])
    ->toOnlyBeUsedIn('App\Features\Auth\Support');
