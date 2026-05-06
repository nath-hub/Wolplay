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
        Schema::table('agenda_items', function (Blueprint $table) {
            // Modifier type (ajout default)
            $table->enum('type', ['live', 'release', 'event'])
                ->default('event')
                ->change();

            // Rendre nullable
            $table->timestamp('scheduled_at')
                ->nullable()
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agenda_items', function (Blueprint $table) {
            // rollback (optionnel)
            $table->enum('type', ['live', 'release', 'event'])->change();
            $table->timestamp('scheduled_at')->nullable(false)->change();
        });
    }
};
