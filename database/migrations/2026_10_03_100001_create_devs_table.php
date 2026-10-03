<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devs', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->string('username', 190);
            $table->string('name', 255);
            $table->text('bio')->nullable();
            $table->string('avatar', 500);
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        // Índices parciais: só linhas ativas (soft delete). Username sem diferenciar caixa nem acento (norm_text).
        DB::statement('create unique index devs_username_norm_unique on devs (norm_text(username)) where deleted_at is null');
        // GET /devs: created_at DESC, id DESC (desempate determinístico).
        DB::statement('create index devs_created_at_id_index on devs (created_at desc, id desc) where deleted_at is null');
    }

    public function down(): void
    {
        Schema::dropIfExists('devs');
    }
};
