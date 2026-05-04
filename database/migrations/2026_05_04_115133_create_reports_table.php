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
        Schema::create('reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('reporter_id')
                ->constrained('users')->onDelete('cascade');

            // Cible polymorphique (video | post)
            $table->string('target_type');               // "video" | "post"
            $table->uuid('target_id');

            $table->enum('reason', [
                'spam',
                'insulte',
                'contenu_inapproprie',
                'fraude_vol_contenu',
            ]);
            $table->enum('status', ['pending', 'reviewed', 'actioned', 'dismissed'])
                ->default('pending');

            $table->timestamp('created_at')->useCurrent();

            $table->index(['target_type', 'target_id']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
