<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('author_id')
                ->constrained('users')->onDelete('cascade');

            $table->enum('post_type', ['video', 'wip', 'photo', 'text']);

            // Contenu selon le type
            $table->text('content')->nullable();           // texte / légende WIP
            $table->string('media_url')->nullable();       // photo ou URL vidéo source
            $table->string('thumbnail_url')->nullable();   // miniature vidéo/photo

            // États spéciaux
            $table->boolean('is_pinned')->default(false);  // WIP épinglé (1 max/créateur)
            $table->boolean('is_wip')->default(false);     // marqueur WIP
            $table->unsignedTinyInteger('wip_progress')->default(0); // 0-100

            $table->enum('status', ['published', 'hidden', 'deleted'])->default('published');

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrentOnUpdate()->nullable();
            $table->softDeletes();

            $table->index(['author_id', 'post_type', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
