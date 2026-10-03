<?php

declare(strict_types=1);

use Database\Seeders\ParityDatasetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function videosOf(string $channel): int
{
    return DB::table('videos')->join('channels', 'channels.id', '=', 'videos.channel_id')->where('channels.name', $channel)->count();
}

it('gera os números do dataset de paridade (35 / 3 / 3 / 55 / 2 / 2)', function (): void {
    $this->seed(ParityDatasetSeeder::class);

    expect(DB::table('devs')->count())->toBe(35);
    expect(DB::table('channels')->count())->toBe(3);
    expect(DB::table('tags')->count())->toBe(3);
    expect(DB::table('videos')->count())->toBe(55);
    expect(DB::table('dev_reactions')->count())->toBe(2);
    expect(DB::table('channel_reactions')->count())->toBe(2);
});

it('reproduz as consequências conferidas no baseline do v1', function (): void {
    $this->seed(ParityDatasetSeeder::class);

    expect(videosOf('Canal Beta'))->toBe(35);

    expect(videosOf('Canal Zeta'))->toBe(0);

    expect(DB::table('devs')->whereNull('bio')->count())->toBe(11); // múltiplos de 3 até 35
    expect(DB::table('channel_tag')->count())->toBe(3);
});

it('usa só o domínio reservado example.test e ids UUIDv7', function (): void {
    $this->seed(ParityDatasetSeeder::class);

    expect(DB::table('devs')->where('avatar', 'not like', 'https://example.test/%')->count())->toBe(0);
    expect(DB::table('devs')->whereRaw("substr(id::text, 15, 1) <> '7'")->count())->toBe(0);
});
