<?php

declare(strict_types=1);

namespace App\Features\Dev\Queries;

use App\Shared\Github\GithubProfile;
use App\Shared\Support\NormText;
use Illuminate\Support\Facades\DB;

/** Escritas do dev: criação sem corrida e reações idempotentes (Fase 1). */
final class DevWriter
{
    /**
     * `INSERT … ON CONFLICT DO NOTHING`: duas requisições simultâneas viram um registro só. Username em minúsculas,
     * como o v1. 1 query.
     */
    public function insertIfMissing(GithubProfile $profile): void
    {
        DB::table('devs')->insertOrIgnore([
            'username' => strtolower($profile->login),
            'name' => $profile->name,
            'bio' => $profile->bio,
            'avatar' => $profile->avatar,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** Username como foi gravado (sem caixa nem acento, `norm_text`), ou `null` se não existe ou foi apagado. 1 query. */
    public function usernameOf(string $username): ?string
    {
        $stored = DB::table('devs')->whereNull('deleted_at')->whereRaw(NormText::equals('username'), [$username])->value('username');

        return is_string($stored) ? $stored : null;
    }

    /** Id do dev ativo. 1 query. */
    public function idOf(string $username): ?string
    {
        $id = DB::table('devs')->whereNull('deleted_at')->whereRaw(NormText::equals('username'), [$username])->value('id');

        return is_string($id) ? $id : null;
    }

    /** `INSERT … ON CONFLICT DO NOTHING` pela PK `(dev_id, target_dev_id, type)`. 1 query. */
    public function addReaction(string $devId, string $targetId, string $type): void
    {
        DB::table('dev_reactions')->insertOrIgnore([
            'dev_id' => $devId,
            'target_dev_id' => $targetId,
            'type' => $type,
            'created_at' => now(),
        ]);
    }

    /** `DELETE` pela PK composta; remover o que não existe não é erro. 1 query. */
    public function removeReaction(string $devId, string $targetId, string $type): void
    {
        DB::table('dev_reactions')->where('dev_id', $devId)->where('target_dev_id', $targetId)->where('type', $type)->delete();
    }
}
