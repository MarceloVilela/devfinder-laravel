<?php

declare(strict_types=1);

use App\Features\Video\Actions\IngestVideos;
use App\Features\Video\Queries\VideoWriter;
use Database\Seeders\ParityDatasetSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(fn() => $this->seed(ParityDatasetSeeder::class));

/**
 * @param array<string, string> $over
 * @return array<string, string>
 */
function candidate(string $id, array $over = []): array
{
    return $over + [
        'title' => "Vídeo {$id}",
        'url' => "https://www.youtube.com/watch?v={$id}",
        'channel' => 'Canal Alpha',
        'channel_url' => 'https://youtube.com/alpha',
        'thumbnail' => "https://i.ytimg.com/vi/{$id}/hqdefault.jpg",
    ];
}

function connectionLost(): QueryException
{
    $pdo = new class ('SQLSTATE[08006] [7] connection failure') extends PDOException {
        /** @var string */
        protected $code = '08006';
    };

    return new QueryException('pgsql', 'select 1', [], $pdo);
}

/** @param list<mixed> $candidates */
function ingest(array $candidates): App\Features\Video\Data\IngestResult
{
    return app(IngestVideos::class)($candidates);
}

it('cobre os três caminhos do v1: novo, duplicado e canal inexistente', function (): void {
    $r = ingest([
        candidate('NEWVID00001'),
        candidate('vidalpha01', ['title' => 'Vídeo Alpha 01']),
        candidate('LOSTVID0001', ['channel' => 'Canal Inexistente XYZ', 'channel_url' => 'https://www.youtube.com/canal-inexistente', 'thumbnail' => '']),
    ]);

    expect($r->videosAdded)->toHaveCount(1)->and($r->videosAdded[0]->title)->toBe('Vídeo NEWVID00001')
        ->and($r->videosFounded)->toHaveCount(1)->and($r->videosFounded[0]->title)->toBe('Vídeo Alpha 01')
        ->and($r->errors)->toHaveCount(1)
        ->and($r->errors[0])->toBe([
            'errorMessage' => 'channel(Canal Inexistente XYZ) not found, for: Vídeo LOSTVID0001',
            'title' => 'Vídeo LOSTVID0001',
            'url' => 'https://www.youtube.com/watch?v=LOSTVID0001',
            'channel' => 'Canal Inexistente XYZ',
            'channel_url' => 'https://www.youtube.com/canal-inexistente',
            'thumbnail' => '',
        ]);
    expect(DB::table('videos')->count())->toBe(56);
});

it('é idempotente: rodar o mesmo lote de novo não grava nada e devolve tudo como encontrado', function (): void {
    $batch = [candidate('IDEMPOT0001'), candidate('IDEMPOT0002')];

    $first = ingest($batch);
    $second = ingest($batch);

    expect($first->videosAdded)->toHaveCount(2)
        ->and($second->videosAdded)->toHaveCount(0)
        ->and($second->videosFounded)->toHaveCount(2)
        ->and(array_column(array_map(static fn($v) => ['id' => $v->id], $second->videosFounded), 'id'))
        ->toBe(array_column(array_map(static fn($v) => ['id' => $v->id], $first->videosAdded), 'id'));
    expect(DB::table('videos')->count())->toBe(57);
});

it('o mesmo url duas vezes no lote: o segundo é encontrado', function (): void {
    $r = ingest([candidate('DUPBATCH001'), candidate('DUPBATCH001', ['title' => 'repetido'])]);

    expect($r->videosAdded)->toHaveCount(1)->and($r->videosFounded)->toHaveCount(1)->and($r->errors)->toBe([]);
});

it('outro url com o mesmo id do YouTube é encontrado (UNIQUE), não erro', function (): void {
    $r = ingest([candidate('SAMEID00001'), candidate('SAMEID00001', ['url' => 'https://www.youtube.com/watch?v=SAMEID00001&list=PL1'])]);

    expect($r->videosAdded)->toHaveCount(1)->and($r->videosFounded)->toHaveCount(1)->and($r->errors)->toBe([]);
    expect(DB::table('videos')->where('youtube_id', 'SAMEID00001')->count())->toBe(1);
});

it('acha o canal por nome, por link e por link alternativo, e o canal de link antigo pelo nome (@handle novo no vídeo)', function (): void {
    DB::table('channels')->where('name', 'Canal Beta')->update(['alternative_link' => 'https://youtube.com/c/beta']);

    $r = ingest([
        candidate('BYNAME00001', ['channel' => 'Canal Beta', 'channel_url' => 'https://nada.test']),
        candidate('BYLINK00001', ['channel' => 'Outro Nome', 'channel_url' => 'https://youtube.com/beta']),
        candidate('BYALT000001', ['channel' => 'Outro Nome', 'channel_url' => 'https://youtube.com/c/beta']),
        candidate('BYHANDLE0001', ['channel' => 'canal beta', 'channel_url' => 'https://www.youtube.com/@canalbeta']),
    ]);

    expect(array_map(static fn($v) => $v->channelName, $r->videosAdded))->toBe(['Canal Beta', 'Canal Beta', 'Canal Beta', 'Canal Beta']);
});

it('tira o &pp= e resolve a thumbnail: hq720_custom vira hqdefault, vazia vira o padrão, outra fica', function (): void {
    $r = ingest([
        candidate('THUMB000001', ['url' => 'https://www.youtube.com/watch?v=THUMB000001&pp=zzz', 'thumbnail' => 'https://i.ytimg.com/vi/THUMB000001/hq720_custom_3.jpg?sqp=a&rs=b']),
        candidate('THUMB000002', ['thumbnail' => '']),
        candidate('THUMB000003', ['thumbnail' => 'https://img.youtube.com/vi/THUMB000003/maxresdefault.jpg&pp=x']),
    ]);

    expect($r->videosAdded[0]->url)->toBe('https://www.youtube.com/watch?v=THUMB000001')
        ->and($r->videosAdded[0]->thumbnail)->toBe('https://i.ytimg.com/vi/THUMB000001/hqdefault.jpg')
        ->and($r->videosAdded[1]->thumbnail)->toBe('https://i.ytimg.com/vi/THUMB000002/hqdefault.jpg')
        ->and($r->videosAdded[2]->thumbnail)->toBe('https://img.youtube.com/vi/THUMB000003/maxresdefault.jpg');
});

it('candidato inválido vira item de errors (o v1 gravava lixo ou dava 500) e o resto do lote segue', function (mixed $bad, string $reason): void {
    $r = ingest([$bad, candidate('AFTERBAD001')]);

    expect($r->videosAdded)->toHaveCount(1)->and($r->errors)->toHaveCount(1);
    expect($r->errors[0]['errorMessage'])->toContain($reason);
})->with([
    'sem v=' => [candidate('x', ['url' => 'https://youtu.be/abc']), 'v='],
    'id com 21 caracteres' => [candidate('x', ['url' => 'https://www.youtube.com/watch?v=' . str_repeat('a', 21)]), 'v='],
    'sem título' => [candidate('NOTITLE0001', ['title' => '']), 'title is required'],
    'texto grande demais' => [candidate('BIGTEXT0001', ['title' => str_repeat('t', 501)]), 'text too long'],
    'não é objeto' => ['texto solto', 'not an object'],
    'nulo' => [null, 'not an object'],
]);

it('um candidato que falha no banco não derruba o lote: vira erro e o resto é gravado', function (): void {
    DB::unprepared('create or replace function falha_video() returns trigger language plpgsql as $$ begin if new.title = \'FALHA\' then raise exception \'falha de teste\'; end if; return new; end $$');
    DB::unprepared('create trigger falha_video before insert on videos for each row execute function falha_video()');

    try {
        $r = ingest([candidate('BEFORE00001'), candidate('FAILING0001', ['title' => 'FALHA']), candidate('AFTER000001')]);
    } finally {
        DB::unprepared('drop trigger falha_video on videos');
        DB::unprepared('drop function falha_video()');
    }

    expect($r->videosAdded)->toHaveCount(2)->and($r->errors)->toHaveCount(1);
    expect($r->errors[0]['errorMessage'])->toBe('could not persist video(FALHA)')->and(json_encode($r->errors))->not->toContain('falha de teste');
});

it('três falhas de conexão seguidas abortam o lote com exceção (F6-4); uma só não', function (): void {
    $writer = Mockery::mock(VideoWriter::class)->makePartial();
    $writer->shouldReceive('channelIdFor')->andThrow(connectionLost());
    app()->instance(VideoWriter::class, $writer);

    expect(fn() => ingest([candidate('CONN0000001'), candidate('CONN0000002'), candidate('CONN0000003'), candidate('CONN0000004')]))
        ->toThrow(QueryException::class);

    $oneOff = Mockery::mock(VideoWriter::class)->makePartial();
    $oneOff->shouldReceive('channelIdFor')->once()->andThrow(connectionLost());
    $oneOff->shouldReceive('channelIdFor')->andReturn(null);
    app()->instance(VideoWriter::class, $oneOff);

    expect(ingest([candidate('CONN0000005'), candidate('CONN0000006')])->errors)->toHaveCount(2);
});

it('respeita o orçamento de queries: canais distintos + inserções + 4', function (): void {
    $batch = array_map(static fn(int $n): array => candidate(sprintf('BUDGET%05d', $n)), range(1, 30));

    $queries = countQueries(fn() => ingest($batch));

    expect($queries)->toBeLessThanOrEqual(1 + 30 + 4);
});

it('vídeo existente cujo canal foi apagado não derruba o lote', function (): void {
    DB::table('channels')->where('name', 'Canal Alpha')->update(['deleted_at' => now()]);

    $r = ingest([candidate('vidalpha01')]);

    expect($r->errors)->toHaveCount(1)->and($r->videosFounded)->toHaveCount(0);
});

it('lote vazio devolve listas vazias', function (): void {
    $r = ingest([]);

    expect($r->videosAdded)->toBe([])->and($r->videosFounded)->toBe([])->and($r->errors)->toBe([]);
});
