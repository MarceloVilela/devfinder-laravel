<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('create extension if not exists unaccent');
        // Busca "contém" com índice GIN (P-5): LIKE '%termo%' sobre norm_text(coluna).
        DB::statement('create extension if not exists pg_trgm');

        // unaccent() é STABLE e não pode entrar em índice; o invólucro IMMUTABLE com o dicionário
        // qualificado é o padrão aceito. norm_text() = sem acento e sem caixa (equivale ao _ai_ci do v1).
        DB::statement(<<<'SQL'
            create or replace function norm_text(text) returns text
            language sql immutable parallel safe strict
            as $$ select lower(public.unaccent('public.unaccent'::regdictionary, $1)) $$
            SQL);
    }

    public function down(): void
    {
        DB::statement('drop function if exists norm_text(text)');
        DB::statement('drop extension if exists pg_trgm');
        DB::statement('drop extension if exists unaccent');
    }
};
