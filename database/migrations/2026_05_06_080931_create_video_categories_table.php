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
        Schema::create('video_categories', function (Blueprint $table) {
            $table->uuid('video_id');
            $table->uuid('categorie_id');

            // Clé primaire composite (évite les doublons)
            $table->primary(['video_id', 'categorie_id']);

            // Clés étrangères
            $table->foreign('video_id')
                ->references('id')
                ->on('videos')
                ->cascadeOnDelete();

            $table->foreign('categorie_id')
                ->references('id')
                ->on('categories')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('video_categories');
    }
};
