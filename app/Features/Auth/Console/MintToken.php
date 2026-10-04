<?php

declare(strict_types=1);

namespace App\Features\Auth\Console;

use App\Features\Auth\Support\TokenCodec;
use Illuminate\Console\Command;

/** Token sintético para os `.http` e a prova local (F4-12). Recusa em produção: lá só o login real emite token. */
final class MintToken extends Command
{
    protected $signature = 'auth:mint-token {username : Dev que assina o token (não precisa existir: serve para o caso do fantasma)}';

    protected $description = 'Emite um JWT de 7 dias para um username (somente fora de produção)';

    public function handle(TokenCodec $tokens): int
    {
        if (app()->isProduction()) {
            $this->error('Recusado em produção.');

            return self::FAILURE;
        }

        $username = $this->argument('username');
        $this->line($tokens->issue(is_string($username) ? $username : ''));

        return self::SUCCESS;
    }
}
