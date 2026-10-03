<?php

declare(strict_types=1);

use Studio\Gesso\Laravel\ValidatesOpenApiSchema;
use Tests\TestCase;

uses(TestCase::class)->in('Feature');
uses(TestCase::class, ValidatesOpenApiSchema::class)->in('Contract');
