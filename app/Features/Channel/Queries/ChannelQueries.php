<?php

declare(strict_types=1);

namespace App\Features\Channel\Queries;

use App\Features\Channel\Models\Channel;
use App\Shared\Support\NormText;
use Illuminate\Database\Eloquent\Collection;

final class ChannelQueries
{
    /**
     * 2 queries: canais e tags de todos (sem paginação, como no v1).
     *
     * @return Collection<int, Channel>
     */
    public function all(): Collection
    {
        return Channel::query()->with('tags')->orderByRaw('norm_text(name)')->orderBy('id')->get();
    }

    /** Canal ativo com as tags (para a resposta de `POST /channels`). 2 queries. */
    public function byId(string $id): ?Channel
    {
        return Channel::query()->with('tags')->whereKey($id)->first();
    }

    /** Por nome, link ou link alternativo, exato e sem caixa nem acento. 2 queries (1 se não existe). */
    public function byNameOrLink(string $searchQuery): ?Channel
    {
        return Channel::query()
            ->with('tags')
            ->whereRaw(
                '(' . NormText::equals('name') . ' or ' . NormText::equals('link') . ' or ' . NormText::equals('alternative_link') . ')',
                [$searchQuery, $searchQuery, $searchQuery],
            )
            ->orderBy('created_at')
            ->orderBy('id')
            ->first();
    }
}
