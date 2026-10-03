<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

it('responde /health sem tocar no banco', function (): void {
    DB::shouldReceive('select')->never();

    $this->getJson('/health')->assertOk()->assertExactJson(['status' => 'ok']);
});

it('responde /health/db com o banco de pé', function (): void {
    $this->getJson('/health/db')->assertOk()->assertExactJson(['status' => 'ok', 'database' => 'up']);
});

it('responde 503 no formato do contrato quando o banco cai, sem vazar a causa', function (): void {
    config(['database.connections.pgsql.host' => '127.0.0.1', 'database.connections.pgsql.port' => 1]);
    DB::purge('pgsql');

    $this->getJson('/health/db')
        ->assertStatus(503)
        ->assertExactJson(['error' => 'Service unavailable.']);
});
