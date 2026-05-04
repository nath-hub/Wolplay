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
        Schema::create('collection_videos', function (Blueprint $table) {
              $table->foreignUuid('collection_id')
                  ->constrained('collections')->onDelete('cascade');
            $table->foreignUuid('video_id')
                  ->constrained('videos')->onDelete('cascade');
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->primary(['collection_id', 'video_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collection_videos');
    }
};
