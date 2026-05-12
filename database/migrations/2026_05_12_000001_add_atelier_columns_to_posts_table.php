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
        Schema::table('posts', function (Blueprint $table) {
            // On vérifie si la colonne 'type' n'existe pas déjà
            if (!Schema::hasColumn('posts', 'type')) {
                $table->enum('type', ['shared_post', 'shared_etabli'])->nullable()->after('post_type');
            }

            if (!Schema::hasColumn('posts', 'source_snapshot')) {
                $table->json('source_snapshot')->nullable();
            }

            if (!Schema::hasColumn('posts', 'images')) {
                // Attention : MySQL ne supporte pas de valeur par défaut (default) sur le type JSON
                // On le met en nullable() ou on retire le default('')
                $table->json('images')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn(['type', 'source_snapshot', 'images']);
        });
    }
};
