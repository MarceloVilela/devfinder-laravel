<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Dataset de paridade do G3 (`specs/dataset-de-paridade.md`): 35 devs, 3 canais, 3 tags, 55 vídeos,
 * 2 reações de dev e 2 de canal. Só dados inventados (`example.test`); nada do dump real.
 */
final class ParityDatasetSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $this->devs();
            $channels = $this->channels();
            $this->tags($channels);
            $this->videos($channels);
            $this->reactions($channels);
        });
    }

    private function devs(): void
    {
        $base = Carbon::parse('2026-01-01 00:00:00', 'UTC');
        $rows = [];

        for ($n = 1; $n <= 35; $n++) {
            $nn = sprintf('%02d', $n);
            $at = $base->copy()->addMinutes($n);
            $rows[] = [
                'username' => "dev{$nn}",
                'name' => "Dev {$nn}",
                'avatar' => "https://example.test/avatar/dev{$nn}.png",
                'bio' => $n % 3 === 0 ? null : "Bio sintética do dev {$nn}.",
                'created_at' => $at,
                'updated_at' => $at,
            ];
        }

        DB::table('devs')->insert($rows);
    }

    /** @return array<string, string> nome do canal => id */
    private function channels(): array
    {
        $base = Carbon::parse('2026-01-01 00:00:00', 'UTC');
        $defs = [
            ['Canal Alpha', 'https://youtube.com/alpha', 'Tecnologia', 'Canal sintético Alpha, usado nos casos de aceite.', 'alpha'],
            ['Canal Beta', 'https://youtube.com/beta', 'Educação', 'Canal sintético Beta, usado nos casos de aceite.', 'beta'],
            ['Canal Zeta', 'https://youtube.com/zeta', 'Testes', 'Canal sintético Zeta — sem vídeos nem reações de baseline, dedicado a testes de escrita (Fase 5).', 'zeta'],
        ];
        $ids = [];

        foreach ($defs as $i => [$name, $link, $category, $description, $slug]) {
            $at = $base->copy()->addSeconds($i);
            $ids[$name] = (string) DB::table('channels')->insertGetId([
                'name' => $name,
                'link' => $link,
                'category' => $category,
                'description' => $description,
                'avatar' => "https://example.test/avatar/canal-{$slug}.png",
                'created_at' => $at,
                'updated_at' => $at,
            ]);
        }

        return $ids;
    }

    /** @param array<string, string> $channels */
    private function tags(array $channels): void
    {
        $tagIds = [];

        foreach (['javascript', 'testes', 'react'] as $name) {
            $tagIds[$name] = (string) DB::table('tags')->insertGetId(['name' => $name]);
        }

        DB::table('channel_tag')->insert([
            ['channel_id' => $channels['Canal Alpha'], 'tag_id' => $tagIds['javascript']],
            ['channel_id' => $channels['Canal Alpha'], 'tag_id' => $tagIds['testes']],
            ['channel_id' => $channels['Canal Beta'], 'tag_id' => $tagIds['react']],
        ]);
    }

    /** @param array<string, string> $channels */
    private function videos(array $channels): void
    {
        $base = Carbon::parse('2026-02-01 00:00:00', 'UTC');
        $rows = [];
        $minute = 0;

        foreach ([['Alpha', 20], ['Beta', 35]] as [$label, $count]) {
            for ($n = 1; $n <= $count; $n++) {
                $nn = sprintf('%02d', $n);
                $youtubeId = 'vid' . strtolower($label) . $nn;
                $at = $base->copy()->addMinutes(++$minute);
                $rows[] = [
                    'youtube_id' => $youtubeId,
                    'title' => "Vídeo {$label} {$nn}",
                    'url' => "https://www.youtube.com/watch?v={$youtubeId}",
                    'channel_id' => $channels["Canal {$label}"],
                    'thumbnail' => "https://i.ytimg.com/vi/{$youtubeId}/hqdefault.jpg",
                    'created_at' => $at,
                    'updated_at' => $at,
                ];
            }
        }

        DB::table('videos')->insert($rows);
    }

    /** @param array<string, string> $channels */
    private function reactions(array $channels): void
    {
        $dev = static function (string $username): string {
            $id = DB::table('devs')->where('username', $username)->value('id');

            return is_string($id) ? $id : throw new RuntimeException("dev não semeado: {$username}");
        };

        DB::table('dev_reactions')->insert([
            ['dev_id' => $dev('dev01'), 'target_dev_id' => $dev('dev02'), 'type' => 'like'],
            ['dev_id' => $dev('dev01'), 'target_dev_id' => $dev('dev03'), 'type' => 'dislike'],
        ]);

        DB::table('channel_reactions')->insert([
            ['dev_id' => $dev('dev01'), 'channel_id' => $channels['Canal Alpha'], 'type' => 'follow'],
            ['dev_id' => $dev('dev01'), 'channel_id' => $channels['Canal Beta'], 'type' => 'ignore'],
        ]);
    }
}
