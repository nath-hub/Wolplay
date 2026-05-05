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
        Schema::create('featured_videos', function (Blueprint $table) {
            $table->foreignUuid('user_id')
                ->constrained('users')->onDelete('cascade');
            $table->foreignUuid('video_id')
                ->constrained('videos')->onDelete('cascade');
            $table->unsignedTinyInteger('slot'); // 1–6

            $table->primary(['user_id', 'video_id']);
            $table->unique(['user_id', 'slot']); // un seul video par slot
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('featured_videos');
    }
};
