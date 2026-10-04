<?php

declare(strict_types=1);

use Database\Seeders\ParityDatasetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

beforeEach(fn() => $this->seed(ParityDatasetSeeder::class));

function fixturePath(): string
{
    return base_path('tests/Fixtures/jsonbin-videos.json');
}

it('com a fixture: o resumo do v1 (1 adicionado, 1 já existia, 1 erro) e exit 0', function (): void {
    $this->artisan('video:refresh', ['--fixture' => fixturePath()])
        ->expectsOutputToContain('JSONBin: 3 candidato(s) encontrado(s).')
        ->expectsOutputToContain('Adicionados: 1 | Já existiam: 1 | Erros: 1')
        ->expectsOutputToContain('channel(Canal Inexistente XYZ) not found')
        ->assertExitCode(0);

    $video = DB::table('videos')->where('youtube_id', 'INGESTNEW01')->first();
    expect($video?->url)->toBe('https://www.youtube.com/watch?v=INGESTNEW01')
        ->and($video?->thumbnail)->toBe('https://i.ytimg.com/vi/INGESTNEW01/hqdefault.jpg');
});

it('a segunda execução é idempotente: nada novo, tudo encontrado', function (): void {
    $this->artisan('video:refresh', ['--fixture' => fixturePath()])->assertExitCode(0);

    $this->artisan('video:refresh', ['--fixture' => fixturePath()])
        ->expectsOutputToContain('Adicionados: 0 | Já existiam: 2 | Erros: 1')
        ->assertExitCode(0);
    expect(DB::table('videos')->count())->toBe(56);
});

it('grava o resumo no log estruturado, sem o texto dos erros', function (): void {
    $log = Log::spy();

    $this->artisan('video:refresh', ['--fixture' => fixturePath()])->assertExitCode(0);

    $log->shouldHaveReceived('info')->with('video:refresh', ['candidates' => 3, 'added' => 1, 'found' => 1, 'errors' => 1])->once();
});

it('sem credenciais e sem fixture: avisa e sai com 0, sem tocar na rede', function (): void {
    config(['devfinder.ingestion.jsonbin.api_key' => null, 'devfinder.ingestion.jsonbin.bin_id' => null]);
    Http::fake();

    $this->artisan('video:refresh')->expectsOutputToContain('nada a fazer')->assertExitCode(0);

    Http::assertNothingSent();
});

it('busca no JSONBin quando configurado e troca channel_name por channel na borda', function (): void {
    config(['devfinder.ingestion.jsonbin.api_key' => 'chave-secreta-do-bin', 'devfinder.ingestion.jsonbin.bin_id' => 'bin123']);
    Http::fake(['api.jsonbin.io/*' => Http::response((array) json_decode((string) file_get_contents(fixturePath()), true))]);

    $this->artisan('video:refresh')->expectsOutputToContain('Adicionados: 1 | Já existiam: 1 | Erros: 1')->assertExitCode(0);

    Http::assertSent(fn($r): bool => $r->url() === 'https://api.jsonbin.io/v3/b/bin123' && $r->hasHeader('X-Master-Key', 'chave-secreta-do-bin'));
});

it('fonte fora do ar depois dos retries: exit 1, erro no log e a chave nunca aparece', function (): void {
    config(['devfinder.ingestion.jsonbin.api_key' => 'chave-secreta-do-bin', 'devfinder.ingestion.jsonbin.bin_id' => 'bin123']);
    Http::fake(['api.jsonbin.io/*' => Http::response('boom', 503)]);
    $log = Log::spy();

    $this->artisan('video:refresh')->doesntExpectOutputToContain('chave-secreta-do-bin')->assertExitCode(1);

    $log->shouldHaveReceived('error')->withArgs(fn(string $message, array $context): bool => $message === 'video:refresh: fonte indisponível' && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), 'chave-secreta-do-bin'))->once();
    expect(DB::table('videos')->count())->toBe(55);
});

it('formato inesperado do bin ou fixture inválida: exit 1 sem gravar', function (): void {
    config(['devfinder.ingestion.jsonbin.api_key' => 'k', 'devfinder.ingestion.jsonbin.bin_id' => 'b']);
    Http::fake(['api.jsonbin.io/*' => Http::response(['record' => ['a' => 'objeto, não lista']])]);

    $this->artisan('video:refresh')->assertExitCode(1);
    $this->artisan('video:refresh', ['--fixture' => '/nao/existe.json'])->assertExitCode(1);
    expect(DB::table('videos')->count())->toBe(55);
});
