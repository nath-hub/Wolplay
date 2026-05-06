<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Supprimer le default (sinon erreur)
        DB::statement("ALTER TABLE agenda_items ALTER COLUMN type DROP DEFAULT");

        // 2. Convertir enum → string
        DB::statement("ALTER TABLE agenda_items ALTER COLUMN type TYPE VARCHAR(255)");

        // 3. Remettre le default
        DB::statement("ALTER TABLE agenda_items ALTER COLUMN type SET DEFAULT 'event'");

        // 4. Autoriser null sur scheduled_at (si pas déjà fait)
        DB::statement("ALTER TABLE agenda_items ALTER COLUMN scheduled_at DROP NOT NULL");
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
