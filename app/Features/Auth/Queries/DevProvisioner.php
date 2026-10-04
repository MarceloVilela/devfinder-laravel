<?php

declare(strict_types=1);

namespace App\Features\Auth\Queries;

use App\Features\Auth\Data\GithubProfile;
use App\Shared\Support\NormText;
use Illuminate\Support\Facades\DB;

final class DevProvisioner
{
    /**
     * Find-or-create sem corrida (Fase 1): `INSERT … ON CONFLICT DO NOTHING` e depois a leitura; duas requisições
     * simultâneas viram um registro só. Username em minúsculas, como o v1 (F4-8). Devolve o username gravado.
     */
    public function ensure(GithubProfile $profile): string
    {
        $username = strtolower($profile->login);

        DB::table('devs')->insertOrIgnore([
            'username' => $username,
            'name' => $profile->name,
            'bio' => $profile->bio,
            'avatar' => $profile->avatar,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $stored = DB::table('devs')
            ->whereNull('deleted_at')
            ->whereRaw(NormText::equals('username'), [$username])
            ->value('username');

        return is_string($stored) ? $stored : $username;
    }
}
