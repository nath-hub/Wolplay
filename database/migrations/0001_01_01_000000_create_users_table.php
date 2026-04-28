<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->string('firstName');
            $table->string('lastName');
            $table->timestamp('email_verified_at')->nullable();

            $table->string('pending_email')->nullable();

            $table->uuid('id')->primary();
            $table->string('public_name')->nullable();
            $table->string('pseudo')->unique();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('avatar_url')->nullable();
            $table->text('bio')->nullable();
            $table->integer('level')->default(1);
            $table->enum('role', ['member', 'creator', 'moderator', 'admin'])->default('member');
            $table->string('status')->default('active');
            $table->string('plan')->default('free');
            $table->string('primary_content_tab')->default('videos');
            $table->timestamp('last_login_at')->nullable();
            $table->boolean('is_banned')->default(false);
            $table->timestamp('pseudo_changed_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });


        Schema::create('handle_history', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade');
            $table->string('old_handle');
            $table->string('new_handle');
            $table->decimal('fee_charged', 8, 2)->default(0.00);
            $table->timestamp('changed_at')->useCurrent();
        });

        Schema::create('user_social_links', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade');
            $table->string('platform');
            $table->string('url');
            $table->timestamps();
        });

        // USER_SUBSCRIPTIONS
        Schema::create('user_subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignUuid('plan_id')->constrained('subscription_plans');
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->enum('status', ['active', 'cancelled', 'expired'])->default('active');
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('user_social_links');
        Schema::dropIfExists('user_subscriptions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
