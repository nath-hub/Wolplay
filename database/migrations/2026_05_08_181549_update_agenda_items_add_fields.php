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

            // image de l’événement
            $table->string('image_url')->nullable()->after('url');

            // fin de l’événement (optionnel mais utile UI)
            $table->timestamp('end_date')->nullable()->after('scheduled_at');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agenda_items', function (Blueprint $table) {
            $table->dropColumn(['image_url', 'end_date']);
        });
    }
};
