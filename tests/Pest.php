<?php

declare(strict_types=1);

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
