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
        Schema::create('channels', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->string('name', 255);
            $table->string('link', 500);
            $table->string('alternative_link', 500)->nullable();
            $table->string('user_github', 190)->nullable();
            $table->text('description')->nullable();
            $table->string('category', 190);
            $table->string('avatar', 500)->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        // Índices parciais (soft delete): um canal apagado libera nome e link. Dedup sem diferenciar caixa nem acento (norm_text).
        DB::statement('create unique index channels_name_norm_unique on channels (norm_text(name)) where deleted_at is null');
        DB::statement('create unique index channels_link_norm_unique on channels (norm_text(link)) where deleted_at is null');
        // Resolução de canal por alternative_link (POST /video, POST /video/refresh).
        DB::statement('create index channels_alternative_link_norm_index on channels (norm_text(alternative_link)) where deleted_at is null');
        // GET /search (P-5): contém, sem caixa nem acento, via trigram. Termo < 3 caracteres cai em varredura (tabela pequena).
        DB::statement('create index channels_name_trgm_index on channels using gin (norm_text(name) gin_trgm_ops) where deleted_at is null');
        DB::statement('create index channels_link_trgm_index on channels using gin (norm_text(link) gin_trgm_ops) where deleted_at is null');
        // GET /description/category ordena por category, name.
        DB::statement('create index channels_category_name_index on channels (category, norm_text(name)) where deleted_at is null');
    }

    public function down(): void
    {
        Schema::dropIfExists('channels');
    }
};
