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
        Schema::create('moderation_actions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('moderator_id')
                ->constrained('users')->onDelete('cascade');

            // Cible polymorphique (video | post | user)
            $table->string('target_type');               // "video" | "post" | "user"
            $table->uuid('target_id');

            $table->enum('action_type', [
                'hide',           // masquer le contenu
                'delete',         // supprimer
                'ban_user',       // bannir l'utilisateur
                'change_category', // changer catégorie vidéo
                'wolplay_pick',   // marquer Sélection Wolplay
                'warn',           // avertissement
            ]);
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['target_type', 'target_id']);
            $table->index('moderator_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('moderation_actions');
    }
};
