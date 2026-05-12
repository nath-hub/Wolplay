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
        Schema::create('etablis', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('creator_id')
                ->constrained('users')->onDelete('cascade');

            $table->string('title');
            $table->text('description')->nullable();
            $table->json('images')->nullable(); // Array d'URLs

            $table->enum('status', ['wip', 'done'])->nullable();
            $table->integer('position')->default(0); // Ordre d'affichage
            $table->boolean('is_pinned')->default(false);

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrentOnUpdate()->nullable();

            $table->index(['creator_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('etablis');
    }
};
