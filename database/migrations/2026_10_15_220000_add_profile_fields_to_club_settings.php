<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_settings', function (Blueprint $table) {
            $table->json('profile')->nullable()->after('eurobank');
            $table->json('public_registration')->nullable()->after('public_rent_enabled');
            $table->timestamp('dpa_accepted_at')->nullable()->after('data_retention_months');
        });
    }

    public function down(): void
    {
        Schema::table('club_settings', function (Blueprint $table) {
            $table->dropColumn(['profile', 'public_registration', 'dpa_accepted_at']);
        });
    }
};
