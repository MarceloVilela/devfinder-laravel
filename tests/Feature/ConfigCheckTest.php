<?php

declare(strict_types=1);

it('passa com a configuração completa e lista só nomes', function (): void {
    $this->artisan('config:check')
        ->expectsOutputToContain('APP_KEY')
        ->assertExitCode(0);
});

it('falha quando falta chave obrigatória e nunca imprime o valor', function (): void {
    config(['database.connections.pgsql.password' => '']);

    $this->artisan('config:check')
        ->expectsOutputToContain('DB_PASSWORD')
        ->assertExitCode(1);
});
