<?php

declare(strict_types=1);

namespace App\Shared\Console;

use App\Shared\Support\ConfigKeys;
use Illuminate\Console\Command;

final class ConfigCheck extends Command
{
    protected $signature = 'config:check';

    protected $description = 'Falha se faltar alguma chave obrigatória (só nomes, nunca valores)';

    public function handle(ConfigKeys $keys): int
    {
        $missing = $keys->missing();

        if ($missing !== []) {
            $this->error('Chaves ausentes: ' . implode(', ', $missing));

            return self::FAILURE;
        }

        $this->info('Configuração completa: ' . implode(', ', $keys->present()));

        return self::SUCCESS;
    }
}
