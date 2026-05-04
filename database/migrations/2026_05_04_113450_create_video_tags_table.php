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
        Schema::create('video_tags', function (Blueprint $table) {
            $table->foreignUuid('video_id')
                ->constrained('videos')->onDelete('cascade');
            $table->foreignUuid('tag_id')
                ->constrained('tags')->onDelete('cascade');

            $table->primary(['video_id', 'tag_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('video_tags');
    }
};
