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
        Schema::create('videos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('creator_id')
                ->constrained('users')->onDelete('cascade');

            // Source
            $table->enum('platform', ['youtube', 'twitch']);
            $table->string('platform_video_id')->nullable(); // ID extrait (ex: "dQw4w9WgXcQ")
            $table->string('embed_url')->nullable();          // URL embed générée

            // Métadonnées
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('thumbnail_url')->nullable();

            // Obligations légales & modération
            $table->boolean('author_certified')->default(false); // case obligatoire §2.2
            $table->boolean('is_featured')->default(false);      // dans les 6 slots mis en avant
            $table->boolean('is_wolplay_pick')->default(false);   // Sélection Wolplay (admin)
            $table->enum('status', ['published', 'hidden', 'broken', 'deleted'])
                ->default('published');

            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['creator_id', 'status']);
            $table->index('is_wolplay_pick'); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('videos');
    }
};



