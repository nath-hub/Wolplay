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
        Schema::create('video_disciplines', function (Blueprint $table) {
             $table->foreignUuid('video_id')
                  ->constrained('videos')->onDelete('cascade');
            $table->foreignUuid('discipline_id')
                  ->constrained('disciplines')->onDelete('cascade');

            $table->primary(['video_id', 'discipline_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('video_disciplines');
    }
};
