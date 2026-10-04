<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// RBAC mínimo (F5-15): uma coluna em `devs`, sem mexer em canais nem vídeos. Todo dev nasce `USER`; `ADMIN` se define direto no banco
// (`update devs set role = 'ADMIN' where username = '...'`). ENUM nativo, como as reações (P-6).
return new class extends Migration {
    public function up(): void
    {
        // Idempotente: `migrate:fresh` apaga tabelas, não tipos.
        DB::statement("do $$ begin if not exists (select 1 from pg_type where typname = 'dev_role') then create type dev_role as enum ('USER', 'ADMIN'); end if; end $$");
        DB::statement("alter table devs add column if not exists role dev_role not null default 'USER'");
    }

    public function down(): void
    {
        DB::statement('alter table devs drop column if exists role');
        DB::statement('drop type if exists dev_role');
    }
};
