<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('athlete_transactions', function (Blueprint $table) {
            $table->dropForeign(['athlete_id']);
        });

        Schema::table('athlete_transactions', function (Blueprint $table) {
            $table->uuid('athlete_id')->nullable()->change();
            $table->foreignUuid('club_member_id')
                ->nullable()
                ->after('athlete_id')
                ->constrained('club_members')
                ->nullOnDelete();
            $table->foreign('athlete_id')
                ->references('id')
                ->on('athletes')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('athlete_transactions', function (Blueprint $table) {
            $table->dropForeign(['club_member_id']);
            $table->dropColumn('club_member_id');
            $table->dropForeign(['athlete_id']);
        });

        Schema::table('athlete_transactions', function (Blueprint $table) {
            $table->uuid('athlete_id')->nullable(false)->change();
            $table->foreign('athlete_id')
                ->references('id')
                ->on('athletes')
                ->cascadeOnDelete();
        });
    }
};
