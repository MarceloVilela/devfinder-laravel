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
        DB::statement("do $$ begin if not exists (select 1 from pg_type where typname = 'dev_reaction_type') then create type dev_reaction_type as enum ('like', 'dislike'); end if; end $$");

        Schema::create('dev_reactions', function (Blueprint $table): void {
            $table->foreignUuid('dev_id')->constrained('devs')->cascadeOnDelete();
            $table->foreignUuid('target_dev_id')->constrained('devs')->cascadeOnDelete();
            $table->timestampTz('created_at')->useCurrent();
        });

        // ENUM nativo (P-6); like e dislike são independentes: o mesmo par pode ter os dois (PK inclui type).
        DB::statement('alter table dev_reactions add column type dev_reaction_type not null');
        DB::statement('alter table dev_reactions add primary key (dev_id, target_dev_id, type)');

        DB::statement('alter table dev_reactions add constraint dev_reactions_not_self_check check (dev_id <> target_dev_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('dev_reactions');
        DB::statement('drop type if exists dev_reaction_type');
    }
};
