<?php

declare(strict_types=1);

namespace App\Features\Channel\Queries;

use App\Shared\Support\NormText;
use Illuminate\Support\Facades\DB;

/** Escritas de canal e reações de canal. Consulta à mão: todo `SELECT` inclui `deleted_at IS NULL`. */
final class ChannelWriter
{
    /**
     * Canal já existente por "contém" (F5-1, paridade com o v1): o nome ou o link existentes contêm o título ou o link novos,
     * sem caixa nem acento. 1 query.
     */
    public function findForStore(string $title, string $link): ?string
    {
        $id = DB::table('channels')
            ->whereNull('deleted_at')
            ->whereRaw('(' . NormText::like('name') . ' or ' . NormText::like('link') . ')', [NormText::containsPattern($title), NormText::containsPattern($link)])
            ->orderBy('created_at')
            ->orderBy('id')
            ->value('id');

        return is_string($id) ? $id : null;
    }

    /**
     * 1 query.
     *
     * @param array<string, mixed> $data
     */
    public function insert(array $data): string
    {
        $id = DB::table('channels')->insertGetId($data + ['created_at' => now(), 'updated_at' => now()]);

        return (string) $id;
    }

    /**
     * 1 query.
     *
     * @param array<string, mixed> $data
     */
    public function update(string $id, array $data): void
    {
        DB::table('channels')->where('id', $id)->update($data + ['updated_at' => now()]);
    }

    /**
     * Substitui as tags do canal por inteiro (como o v1 e o original), sem repetir. Tag nova por `INSERT … ON CONFLICT DO NOTHING`
     * e leitura (corrida vira uma tag só). 3 queries ao criar, 4 ao atualizar.
     *
     * @param list<string> $names
     */
    public function syncTags(string $channelId, array $names, bool $replace): void
    {
        $names = array_values(array_unique(array_filter($names, static fn(string $name): bool => $name !== '')));

        if ($replace) {
            DB::table('channel_tag')->where('channel_id', $channelId)->delete();
        }

        if ($names === []) {
            return;
        }

        DB::table('tags')->insertOrIgnore(array_map(static fn(string $name): array => ['name' => $name], $names));

        $ids = DB::table('tags')->whereNull('deleted_at')->whereIn('name', $names)->pluck('id')->all();

        DB::table('channel_tag')->insertOrIgnore(array_map(
            static fn(mixed $tagId): array => ['channel_id' => $channelId, 'tag_id' => $tagId],
            $ids,
        ));
    }

    /** Canal ativo por nome exato (sem caixa nem acento), nunca por link (F5-5). 1 query. */
    public function idByName(string $name): ?string
    {
        $id = DB::table('channels')->whereNull('deleted_at')->whereRaw(NormText::equals('name'), [$name])->value('id');

        return is_string($id) ? $id : null;
    }

    /** 1 query. */
    public function addReaction(string $devId, string $channelId, string $type): void
    {
        DB::table('channel_reactions')->insertOrIgnore([
            'dev_id' => $devId,
            'channel_id' => $channelId,
            'type' => $type,
            'created_at' => now(),
        ]);
    }

    /** 1 query. */
    public function removeReaction(string $devId, string $channelId, string $type): void
    {
        DB::table('channel_reactions')->where('dev_id', $devId)->where('channel_id', $channelId)->where('type', $type)->delete();
    }
}
