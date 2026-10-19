<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_activity_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 64);
            $table->string('summary');
            $table->text('description');
            $table->string('subject_type', 64)->nullable();
            $table->string('subject_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamps();

            $table->index(['club_id', 'occurred_at']);
            $table->index(['club_id', 'user_id', 'occurred_at']);
            $table->index(['club_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_activity_logs');
    }
};
