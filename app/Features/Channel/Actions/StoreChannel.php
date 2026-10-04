<?php

declare(strict_types=1);

namespace App\Features\Channel\Actions;

use App\Features\Channel\Data\StoreChannelData;
use App\Features\Channel\Data\StoredChannel;
use App\Features\Channel\Exceptions\ChannelAlreadyExists;
use App\Features\Channel\Queries\ChannelWriter;
use App\Features\Dev\Actions\EnsureDev;
use App\Shared\Exceptions\GithubUnavailable;
use App\Shared\Github\GithubClient;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * `POST /channels`: acha o canal existente por "contém" (F5-1) e o atualiza (200), senão cria (201). Canal, tags e vínculos numa
 * transação; o dev de `userGithub` só na criação e fora da transação (a chamada ao GitHub não segura conexão aberta, F5-9).
 */
final class StoreChannel
{
    public function __construct(
        private readonly ChannelWriter $channels,
        private readonly GithubClient $github,
        private readonly EnsureDev $ensureDev,
    ) {}

    /** @throws ChannelAlreadyExists */
    public function __invoke(StoreChannelData $data): StoredChannel
    {
        try {
            $stored = DB::transaction(fn(): StoredChannel => $this->save($data));
        } catch (UniqueConstraintViolationException $e) {
            throw new ChannelAlreadyExists('channel name or link already taken', $e);
        }

        if ($stored->created && $data->userGithub !== null) {
            $this->provisionOwner($data->userGithub);
        }

        return $stored;
    }

    private function save(StoreChannelData $data): StoredChannel
    {
        $existing = $this->channels->findForStore($data->title, $data->link);
        $columns = [
            'name' => $data->title,
            'link' => $data->link,
            'user_github' => $data->userGithub,
            'description' => $data->description,
            'category' => self::withoutEmoji($data->category),
            'avatar' => $data->avatar,
        ];

        if ($existing !== null) {
            $this->channels->update($existing, $columns);
            $this->channels->syncTags($existing, $data->tags, replace: true);

            return new StoredChannel($existing, created: false);
        }

        $id = $this->channels->insert($columns);
        $this->channels->syncTags($id, $data->tags, replace: false);

        return new StoredChannel($id, created: true);
    }

    /** Falha do GitHub (404 ou fora do ar) não derruba a criação do canal: vai para o log (F5-9). */
    private function provisionOwner(string $login): void
    {
        try {
            $profile = $this->github->publicProfile($login);

            if ($profile !== null) {
                ($this->ensureDev)($profile);
            }
        } catch (GithubUnavailable $e) {
            Log::warning('github indisponível ao criar o dev de userGithub', ['login' => $login, 'cause' => $e->getMessage()]);
        }
    }

    /** `category` sem emoji (`U+E000–F8FF`, `U+1F300–1F5FF`) e sem cortar espaço: o original não faz `trim`. */
    public static function withoutEmoji(string $category): string
    {
        return preg_replace('/[\x{E000}-\x{F8FF}\x{1F300}-\x{1F5FF}]/u', '', $category) ?? $category;
    }
}
