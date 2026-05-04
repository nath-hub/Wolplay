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
        Schema::create('user_disciplines', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')
                  ->constrained('users')->onDelete('cascade');
            $table->foreignUuid('discipline_id')
                  ->constrained('disciplines')->onDelete('cascade');
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'discipline_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_disciplines');
    }
};
