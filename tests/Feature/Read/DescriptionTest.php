<?php

declare(strict_types=1);

use Database\Seeders\ParityDatasetSeeder;

beforeEach(fn() => $this->seed(ParityDatasetSeeder::class));

it('monta o texto do feed só com o Canal Beta e a hashtag #react', function (): void {
    $response = $this->get('/v1/description/feed')->assertOk();

    expect($response->headers->get('Content-Type'))->toStartWith('text/html')
        ->and($response->getContent())->toBe(
            'https://devfinder.vercel.app | Adicionados novos vídeos | <br /><br />'
            . 'Canal Beta<br /><br />'
            . 'Repositório da aplicação web: https://github.com/marcelovilela/devfinder-next <br /><br />'
            . 'Meu github: https://github.com/marcelovilela <br /><br />'
            . '#react',
        );
});

it('monta um texto por categoria, em ordem de categoria e unidos por quebra de linha', function (): void {
    $response = $this->get('/v1/description/category')->assertOk();
    $body = $response->getContent();

    expect($response->headers->get('Content-Type'))->toStartWith('text/html')
        ->and($body)->toStartWith('Encontre canais sobre Educação em https://devfinder.vercel.app/channel <br /><br />Canal Beta<br /><br />')
        ->and($body)->toContain("<br /><br />--------------------<br /><br />\nEncontre canais sobre Tecnologia ")
        ->and($body)->toContain('Canal Alpha<br /><br />')
        ->and($body)->toContain('#javascript<br />#testes<br /><br />--------------------<br /><br />')
        ->and($body)->toContain("Encontre canais sobre Testes em https://devfinder.vercel.app/channel <br /><br />Canal Zeta<br /><br />");
});

it('responde corpo vazio sem canais', function (): void {
    DB::table('channel_reactions')->delete();
    DB::table('videos')->delete();
    DB::table('channels')->delete();

    expect($this->get('/v1/description/category')->assertOk()->getContent())->toBe('');
    expect($this->get('/v1/description/feed')->assertOk()->getContent())->toContain('Adicionados novos vídeos | <br /><br /><br /><br />Repositório');
});
