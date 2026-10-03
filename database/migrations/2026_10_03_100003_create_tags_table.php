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
        Schema::create('tags', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->string('name', 100);
            $table->softDeletesTz();
        });

        DB::statement('create unique index tags_name_unique on tags (name) where deleted_at is null');
    }

    public function down(): void
    {
        Schema::dropIfExists('tags');
    }
};
