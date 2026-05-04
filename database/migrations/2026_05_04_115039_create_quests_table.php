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
        Schema::create('quests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('creator_id')             // créateur ciblé par la quête
                ->constrained('users')->onDelete('cascade');
            $table->foreignUuid('sponsor_id')->nullable() // parrain (user ou null si externe)
                ->constrained('users')->onDelete('set null');

            $table->string('sponsor_name')->nullable();   // nom sponsor externe
            $table->string('sponsor_logo_url')->nullable();

            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('eclats_goal');       // objectif en Éclats
            $table->unsignedInteger('eclats_collected')->default(0);

            $table->enum('status', ['pending', 'active', 'completed', 'cancelled'])
                ->default('pending');

            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            // Tirage final (§3.2 — 1 contributeur = 1 chance)
            $table->foreignUuid('winner_id')->nullable()
                ->constrained('users')->onDelete('set null');
            $table->timestamp('drawn_at')->nullable();

            $table->timestamps();

            $table->index(['creator_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quests');
    }
};
