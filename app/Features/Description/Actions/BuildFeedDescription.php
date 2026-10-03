<?php

declare(strict_types=1);

namespace App\Features\Description\Actions;

use App\Features\Description\Queries\DescriptionQueries;

/** Texto compartilhável do feed em alta (formato do v1, byte a byte). */
final class BuildFeedDescription
{
    public function __construct(private readonly DescriptionQueries $queries) {}

    public function __invoke(): string
    {
        $channels = $this->queries->trendingChannels(30);

        $text = 'https://devfinder.vercel.app | Adicionados novos vídeos | <br /><br />';
        $text .= implode('<br />', array_column($channels, 'name')) . '<br /><br />';
        $text .= 'Repositório da aplicação web: https://github.com/marcelovilela/devfinder-next <br /><br />';
        $text .= 'Meu github: https://github.com/marcelovilela <br /><br />';
        $text .= implode('<br />', Hashtags::from($channels));

        return $text;
    }
}
