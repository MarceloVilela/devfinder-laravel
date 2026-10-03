<?php

declare(strict_types=1);

use App\Shared\Support\ConfigKeys;
use Illuminate\Config\Repository;

it('separa chaves presentes das ausentes, só por nome', function (): void {
    $config = new Repository([
        'devfinder' => ['required' => ['APP_KEY' => 'app.key', 'DB_PASSWORD' => 'db.password', 'DB_HOST' => 'db.host']],
        'app' => ['key' => 'base64:segredo'],
        'db' => ['password' => '', 'host' => null],
    ]);

    $keys = new ConfigKeys($config);

    expect($keys->present())->toBe(['APP_KEY']);
    expect($keys->missing())->toBe(['DB_PASSWORD', 'DB_HOST']);
    expect(json_encode([$keys->present(), $keys->missing()]))->not->toContain('segredo');
});
