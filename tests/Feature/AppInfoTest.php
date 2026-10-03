<?php

declare(strict_types=1);

it('GET /v1 devolve o AppInfo do contrato', function (): void {
    $this->getJson('/v1')->assertOk()->assertExactJson(['appname' => 'DevFinder']);
});
