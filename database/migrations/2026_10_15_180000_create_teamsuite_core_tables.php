<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('license_packages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->unsignedInteger('athlete_cap')->default(100);
            $table->json('modules')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('clubs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->foreignUuid('license_package_id')->nullable()->constrained('license_packages')->nullOnDelete();
            $table->json('enabled_modules')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('primary_color', 32)->nullable();
            $table->string('accent_color', 32)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('club_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('club_id')->unique()->constrained('clubs')->cascadeOnDelete();
            // encrypted:array stores ciphertext — MySQL JSON columns reject that.
            $table->longText('smtp')->nullable();
            $table->longText('sms')->nullable();
            $table->longText('push')->nullable();
            $table->longText('viva')->nullable();
            $table->longText('stripe')->nullable();
            $table->longText('eurobank')->nullable();
            $table->boolean('public_join_enabled')->default(true);
            $table->boolean('public_rent_enabled')->default(false);
            $table->longText('terms_of_use_html')->nullable();
            $table->longText('dpa_html')->nullable();
            $table->longText('retention_policy_html')->nullable();
            $table->unsignedInteger('data_retention_months')->default(24);
            $table->timestamps();
        });

        Schema::create('club_user', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 32);
            $table->json('permissions')->nullable();
            $table->boolean('finance_own_entries_only')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['club_id', 'user_id']);
            $table->index(['user_id', 'role']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_platform_admin')->default(false)->after('email');
            $table->uuid('active_club_id')->nullable()->after('is_platform_admin');
            $table->foreign('active_club_id')->references('id')->on('clubs')->nullOnDelete();
        });

        Schema::create('login_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->uuid('club_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('logged_in_at');
            $table->timestamps();
            $table->index(['user_id', 'logged_in_at']);
        });

        Schema::create('club_backups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('club_id')->nullable()->constrained('clubs')->nullOnDelete();
            $table->string('scope', 16); // club|platform
            $table->string('source', 32)->default('upload'); // upload|scheduled|export
            $table->string('original_filename')->nullable();
            $table->string('storage_path')->nullable();
            $table->timestamp('exported_at')->nullable();
            $table->string('source_club_id')->nullable();
            $table->json('dry_run_counts')->nullable();
            $table->string('status', 32)->default('pending');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_backups');
        Schema::dropIfExists('login_activities');
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['active_club_id']);
            $table->dropColumn(['is_platform_admin', 'active_club_id']);
        });
        Schema::dropIfExists('club_user');
        Schema::dropIfExists('club_settings');
        Schema::dropIfExists('clubs');
        Schema::dropIfExists('license_packages');
    }
};
