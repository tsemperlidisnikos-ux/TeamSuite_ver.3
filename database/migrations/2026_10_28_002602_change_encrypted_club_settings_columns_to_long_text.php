<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * encrypted:array stores ciphertext strings. MySQL native JSON columns
     * validate content and reject that payload (SQLSTATE 22032 / 3140).
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        foreach (['smtp', 'sms', 'push', 'viva', 'stripe', 'eurobank'] as $column) {
            DB::statement("ALTER TABLE club_settings MODIFY `{$column}` LONGTEXT NULL");
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        foreach (['smtp', 'sms', 'push', 'viva', 'stripe', 'eurobank'] as $column) {
            DB::statement("ALTER TABLE club_settings MODIFY `{$column}` JSON NULL");
        }
    }
};
