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
        Schema::create('quest_contributions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('quest_id')
                ->constrained('quests')->onDelete('cascade');
            $table->foreignUuid('contributor_id')
                ->constrained('users')->onDelete('cascade');
            $table->unsignedInteger('eclats_amount');
            $table->timestamp('contributed_at')->useCurrent();

            $table->index(['quest_id', 'contributor_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quest_contributions');
    }
};
