<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('videos', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->string('youtube_id', 20);
            $table->string('title', 500);
            $table->string('url', 500);
            $table->foreignUuid('channel_id')->constrained()->restrictOnDelete();
            $table->string('thumbnail', 500);
            $table->unsignedInteger('viewnum')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        // Índices parciais (soft delete): vídeo apagado libera youtube_id e url.
        DB::statement('create unique index videos_youtube_id_unique on videos (youtube_id) where deleted_at is null');
        DB::statement('create unique index videos_url_unique on videos (url) where deleted_at is null');
        // GET /feed/trending: created_at DESC, id DESC.
        DB::statement('create index videos_created_at_id_index on videos (created_at desc, id desc) where deleted_at is null');
        // GET /feed/channel: vídeos de um canal, mesma ordem.
        DB::statement('create index videos_channel_created_at_id_index on videos (channel_id, created_at desc, id desc) where deleted_at is null');
        // GET /search (P-5): contém, sem caixa nem acento, via trigram.
        DB::statement('create index videos_title_trgm_index on videos using gin (norm_text(title) gin_trgm_ops) where deleted_at is null');
    }

    public function down(): void
    {
        Schema::dropIfExists('videos');
    }
};
