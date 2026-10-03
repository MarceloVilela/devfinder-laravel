<?php

declare(strict_types=1);

namespace App\Shared\Support;

use Illuminate\Contracts\Config\Repository;

/** Confere as chaves obrigatórias de `config/devfinder.php`. Nunca expõe valores, só nomes. */
final class ConfigKeys
{
    public function __construct(private readonly Repository $config) {}

    /** @return list<string> nomes das variáveis presentes */
    public function present(): array
    {
        return array_keys(array_filter($this->required(), $this->filled(...)));
    }

    /** @return list<string> nomes das variáveis ausentes */
    public function missing(): array
    {
        return array_keys(array_filter($this->required(), fn(string $key): bool => ! $this->filled($key)));
    }

    /** @return array<string, string> */
    private function required(): array
    {
        /** @var array<string, string> $required */
        $required = $this->config->get('devfinder.required', []);

        return $required;
    }

    private function filled(string $key): bool
    {
        $value = $this->config->get($key);

        return $value !== null && $value !== '' && $value !== [];
    }
}
