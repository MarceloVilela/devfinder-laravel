<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Idempotente: `migrate:fresh` apaga tabelas, não tipos (sem --drop-types o `create type` quebraria na 2ª rodada).
        DB::statement("do $$ begin if not exists (select 1 from pg_type where typname = 'channel_reaction_type') then create type channel_reaction_type as enum ('follow', 'ignore'); end if; end $$");

        Schema::create('channel_reactions', function (Blueprint $table): void {
            $table->foreignUuid('dev_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('channel_id')->constrained()->cascadeOnDelete();
            $table->timestampTz('created_at')->useCurrent();
        });

        // ENUM nativo (P-6); follow e ignore são independentes: o mesmo par pode ter os dois (PK inclui type).
        DB::statement('alter table channel_reactions add column type channel_reaction_type not null');
        DB::statement('alter table channel_reactions add primary key (dev_id, channel_id, type)');

    }

    public function down(): void
    {
        Schema::dropIfExists('channel_reactions');
        DB::statement('drop type if exists channel_reaction_type');
    }
};
