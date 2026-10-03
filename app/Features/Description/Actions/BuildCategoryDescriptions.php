<?php

declare(strict_types=1);

namespace App\Features\Description\Actions;

use App\Features\Description\Queries\DescriptionQueries;

/** Um texto por categoria de canal, unidos por `\n` (decisão herdada do v1: não o `toString()` do array do Express). */
final class BuildCategoryDescriptions
{
    public function __construct(private readonly DescriptionQueries $queries) {}

    public function __invoke(): string
    {
        $byCategory = [];

        foreach ($this->queries->channelsByCategory() as $channel) {
            $byCategory[$channel['category']][] = $channel;
        }

        $descriptions = [];

        foreach ($byCategory as $category => $channels) {
            $text = "Encontre canais sobre {$category} em https://devfinder.vercel.app/channel <br /><br />";
            $text .= implode('<br />', array_column($channels, 'name')) . '<br /><br />';
            $text .= 'Repositório da aplicação web: https://github.com/marcelovilela/devfinder-next <br /><br />';
            $text .= 'Meu github: https://github.com/marcelovilela <br /><br />';
            $text .= implode('<br />', Hashtags::from($channels));
            $text .= '<br /><br />--------------------<br /><br />';
            $descriptions[] = $text;
        }

        return implode("\n", $descriptions);
    }
}
