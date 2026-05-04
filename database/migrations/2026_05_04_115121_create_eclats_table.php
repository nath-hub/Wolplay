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
        Schema::create('eclats', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sender_id')
                ->constrained('users')->onDelete('cascade');
            $table->foreignUuid('receiver_id')
                ->constrained('users')->onDelete('cascade');
            $table->foreignUuid('quest_id')->nullable()
                ->constrained('quests')->onDelete('set null');
            $table->unsignedInteger('amount');
            $table->timestamp('sent_at')->useCurrent();

            $table->index(['receiver_id', 'sent_at']);
            $table->index('quest_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('eclats');
    }
};
