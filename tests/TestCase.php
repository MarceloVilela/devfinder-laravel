<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Fixos aqui, não só no phpunit.xml: um `.env` local com as mesmas chaves não pode mudar o resultado dos testes.
        config([
            'logging.default' => 'null',
            'devfinder.auth.jwt_secret' => 'testing-secret-with-at-least-32-bytes-0123456789',
            'devfinder.auth.web_url' => 'https://app.example.test',
            'devfinder.auth.github.client_id' => 'test-client-id',
            'devfinder.auth.github.client_secret' => 'test-client-secret',
            'devfinder.auth.github.redirect_uri' => null,
        ]);
    }
}
