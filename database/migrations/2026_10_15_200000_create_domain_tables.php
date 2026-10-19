<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->string('name');
            $table->string('legacy_id')->nullable()->index();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('club_seasons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->string('name');
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->boolean('is_active')->default(false);
            $table->string('legacy_id')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('associations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->string('name');
            $table->string('legacy_id')->nullable()->index();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('coaches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('sport')->nullable();
            $table->date('hire_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('gga_code')->nullable();
            $table->string('license_level', 8)->nullable();
            $table->string('legacy_id')->nullable()->index();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('staff_members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('role_title')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('legacy_id')->nullable()->index();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('club_members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('member_number')->nullable();
            $table->string('status', 32)->default('active');
            $table->string('legacy_id')->nullable()->index();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('academy_classes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->foreignUuid('coach_id')->nullable()->constrained('coaches')->nullOnDelete();
            $table->foreignUuid('club_season_id')->nullable()->constrained('club_seasons')->nullOnDelete();
            $table->string('name');
            $table->string('sport')->nullable();
            $table->string('age_group')->nullable();
            $table->string('gender', 16)->nullable();
            $table->unsignedInteger('max_students')->default(30);
            $table->decimal('monthly_fee', 10, 2)->default(0);
            $table->string('schedule_summary')->nullable();
            $table->string('legacy_id')->nullable()->index();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('athletes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('status', 16)->default('active'); // active|inactive|trial
            $table->decimal('monthly_fee', 10, 2)->default(0);
            $table->date('enrolled_at')->nullable();
            $table->text('amka_encrypted')->nullable();
            $table->string('amka_fingerprint', 64)->nullable()->index();
            $table->string('gender', 16)->nullable();
            $table->string('guardian_name')->nullable();
            $table->string('guardian_phone')->nullable();
            $table->string('sport')->nullable();
            $table->boolean('health_card')->default(false);
            $table->date('health_card_expires')->nullable();
            $table->string('gdpr_consent', 16)->nullable();
            $table->string('legacy_id')->nullable()->index();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['club_id', 'status']);
            $table->index(['club_id', 'last_name', 'first_name']);
        });

        Schema::create('academy_class_athlete', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('academy_class_id')->constrained('academy_classes')->cascadeOnDelete();
            $table->foreignUuid('athlete_id')->constrained('athletes')->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->unique(['academy_class_id', 'athlete_id']);
        });

        Schema::create('parent_athlete_links', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->foreignId('parent_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('athlete_id')->constrained('athletes')->cascadeOnDelete();
            $table->string('relation')->nullable();
            $table->boolean('receives_push')->default(true);
            $table->boolean('receives_sms')->default(true);
            $table->string('legacy_id')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['parent_user_id', 'athlete_id']);
        });

        Schema::create('athlete_change_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->foreignUuid('athlete_id')->constrained('athletes')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('field')->nullable();
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->string('legacy_id')->nullable();
            $table->timestamps();
        });

        Schema::create('facilities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->string('name');
            $table->string('address')->nullable();
            $table->boolean('is_rentable')->default(false);
            $table->string('legacy_id')->nullable()->index();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('schedule_slots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->foreignUuid('academy_class_id')->nullable()->constrained('academy_classes')->nullOnDelete();
            $table->foreignUuid('facility_id')->nullable()->constrained('facilities')->nullOnDelete();
            $table->unsignedTinyInteger('weekday')->nullable();
            $table->time('starts_at')->nullable();
            $table->time('ends_at')->nullable();
            $table->string('title')->nullable();
            $table->string('legacy_id')->nullable()->index();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('trainings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->foreignUuid('academy_class_id')->nullable()->constrained('academy_classes')->nullOnDelete();
            $table->foreignUuid('coach_id')->nullable()->constrained('coaches')->nullOnDelete();
            $table->foreignUuid('facility_id')->nullable()->constrained('facilities')->nullOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->string('title')->nullable();
            $table->string('status', 32)->default('scheduled');
            $table->text('notes')->nullable();
            $table->string('legacy_id')->nullable()->index();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('attendances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->foreignUuid('training_id')->nullable()->constrained('trainings')->nullOnDelete();
            $table->foreignUuid('athlete_id')->constrained('athletes')->cascadeOnDelete();
            $table->string('status', 32)->default('present'); // present|absent|late|excused
            $table->date('attended_on')->nullable();
            $table->string('legacy_id')->nullable()->index();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['training_id', 'athlete_id']);
        });

        Schema::create('matches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->foreignUuid('academy_class_id')->nullable()->constrained('academy_classes')->nullOnDelete();
            $table->dateTime('starts_at');
            $table->string('opponent')->nullable();
            $table->string('venue')->nullable();
            $table->string('home_away', 16)->nullable();
            $table->unsignedSmallInteger('score_for')->nullable();
            $table->unsignedSmallInteger('score_against')->nullable();
            $table->string('status', 32)->default('scheduled');
            $table->string('legacy_id')->nullable()->index();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('progress_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->foreignUuid('athlete_id')->constrained('athletes')->cascadeOnDelete();
            $table->foreignUuid('coach_id')->nullable()->constrained('coaches')->nullOnDelete();
            $table->date('report_date')->nullable();
            $table->text('body')->nullable();
            $table->string('legacy_id')->nullable()->index();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('fee_charge_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->string('name');
            $table->decimal('amount', 10, 2)->default(0);
            $table->string('applies_to')->nullable(); // class id / none
            $table->boolean('auto_generate')->default(true);
            $table->boolean('is_active')->default(true);
            $table->string('legacy_id')->nullable()->index();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('athlete_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->foreignUuid('athlete_id')->constrained('athletes')->cascadeOnDelete();
            $table->string('type', 16); // charge|payment
            $table->decimal('amount', 10, 2);
            $table->unsignedTinyInteger('month')->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('payment_method', 32)->nullable();
            $table->string('receipt_number')->nullable();
            $table->string('receipt_series', 16)->nullable();
            $table->unsignedInteger('receipt_seq')->nullable();
            $table->uuid('allocates_charge_id')->nullable();
            $table->text('comments')->nullable();
            $table->string('legacy_id')->nullable()->index();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['club_id', 'type', 'year', 'month']);
        });

        Schema::create('receipt_series', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->string('series', 32);
            $table->unsignedInteger('range_from')->default(1);
            $table->unsignedInteger('range_to')->nullable();
            $table->unsignedInteger('next_number')->default(1);
            $table->boolean('is_active')->default(true);
            $table->string('legacy_id')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('receipt_issues', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->foreignUuid('receipt_series_id')->nullable()->constrained('receipt_series')->nullOnDelete();
            $table->foreignUuid('athlete_transaction_id')->nullable()->constrained('athlete_transactions')->nullOnDelete();
            $table->string('series', 32)->nullable();
            $table->unsignedInteger('number');
            $table->string('status', 16)->default('issued'); // issued|cancelled
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('legacy_id')->nullable()->index();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->unique(['club_id', 'series', 'number']);
        });

        Schema::create('cash_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->string('name');
            $table->decimal('opening_balance', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('legacy_id')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('revenues', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->foreignUuid('cash_account_id')->nullable()->constrained('cash_accounts')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('category', 64)->nullable();
            $table->date('occurred_on')->nullable();
            $table->string('description')->nullable();
            $table->string('legacy_id')->nullable()->index();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->foreignUuid('cash_account_id')->nullable()->constrained('cash_accounts')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('category', 64)->nullable();
            $table->date('occurred_on')->nullable();
            $table->string('description')->nullable();
            $table->string('legacy_id')->nullable()->index();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('budget_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->string('label');
            $table->unsignedSmallInteger('year');
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('legacy_id')->nullable()->index();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('closed_finance_months', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->string('year_month', 7); // YYYY-MM
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->unique(['club_id', 'year_month']);
        });

        Schema::create('discount_reasons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->string('name');
            $table->string('sport')->nullable();
            $table->decimal('default_amount', 10, 2)->nullable();
            $table->string('legacy_id')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('online_checkouts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->foreignUuid('athlete_id')->nullable()->constrained('athletes')->nullOnDelete();
            $table->string('provider', 32); // viva|stripe|eurobank
            $table->string('order_code')->index();
            $table->decimal('amount', 10, 2);
            $table->string('status', 32)->default('pending');
            $table->string('idempotency_key')->nullable()->unique();
            $table->json('payload')->nullable();
            $table->string('legacy_id')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('payment_settlements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->foreignUuid('online_checkout_id')->nullable()->constrained('online_checkouts')->nullOnDelete();
            $table->string('provider', 32);
            $table->string('external_id')->nullable()->index();
            $table->string('idempotency_key')->unique();
            $table->decimal('amount', 10, 2)->nullable();
            $table->string('status', 32)->default('received');
            $table->json('payload')->nullable();
            $table->timestamps();
        });

        Schema::create('warehouse_products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->string('name');
            $table->string('sku')->nullable();
            $table->integer('stock_qty')->default(0);
            $table->integer('low_stock_threshold')->default(5);
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->string('legacy_id')->nullable()->index();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->foreignUuid('warehouse_product_id')->constrained('warehouse_products')->cascadeOnDelete();
            $table->integer('quantity');
            $table->string('reason')->nullable();
            $table->string('legacy_id')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('clothing_packages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->string('name');
            $table->decimal('price', 10, 2)->default(0);
            $table->string('legacy_id')->nullable()->index();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('size_charts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->json('chart');
            $table->timestamps();
        });

        Schema::create('rental_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('club_id')->unique()->constrained('clubs')->cascadeOnDelete();
            $table->boolean('public_enabled')->default(false);
            $table->json('rules')->nullable();
            $table->timestamps();
        });

        Schema::create('rental_bookings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->foreignUuid('facility_id')->nullable()->constrained('facilities')->nullOnDelete();
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_phone')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->decimal('amount', 10, 2)->default(0);
            $table->string('status', 32)->default('pending');
            $table->string('legacy_id')->nullable()->index();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('registration_applications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->string('type', 16)->default('full'); // full|trial|waitlist
            $table->string('status', 16)->default('pending');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->json('form_snapshot')->nullable();
            $table->boolean('gdpr_accepted')->default(false);
            $table->string('signature_path')->nullable();
            $table->string('legacy_id')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('announcements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('body')->nullable();
            $table->json('audience')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('legacy_id')->nullable()->index();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('email_unsubscribes', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->string('email');
            $table->timestamps();
            $table->unique(['club_id', 'email']);
        });

        Schema::create('gallery_photos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->string('path');
            $table->boolean('gdpr_cleared')->default(false);
            $table->string('legacy_id')->nullable()->index();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('media_files', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('club_id')->nullable()->constrained('clubs')->cascadeOnDelete();
            $table->string('disk')->default('public');
            $table->string('path');
            $table->string('kind', 32)->nullable();
            $table->string('original_name')->nullable();
            $table->timestamps();
        });

        Schema::create('partner_businesses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->string('name');
            $table->string('contact')->nullable();
            $table->string('legacy_id')->nullable()->index();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('partner_offers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->foreignUuid('partner_business_id')->constrained('partner_businesses')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('legacy_id')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('document_protocol_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->string('direction', 16); // in|out
            $table->unsignedInteger('protocol_number');
            $table->string('subject');
            $table->date('document_date')->nullable();
            $table->string('legacy_id')->nullable()->index();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['club_id', 'protocol_number']);
        });

        Schema::create('amka_access_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->foreignUuid('athlete_id')->nullable()->constrained('athletes')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 64);
            $table->string('legacy_id')->nullable();
            $table->timestamps();
        });

        Schema::create('gdpr_audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 64);
            $table->json('meta')->nullable();
            $table->string('legacy_id')->nullable();
            $table->timestamps();
        });

        Schema::create('suppressed_fee_charge_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->string('suppress_key');
            $table->timestamps();
            $table->unique(['club_id', 'suppress_key']);
        });

        Schema::create('platform_waitlist', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('club_name')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('status', 32)->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $tables = [
            'platform_waitlist', 'suppressed_fee_charge_keys', 'gdpr_audit_logs', 'amka_access_logs',
            'document_protocol_entries', 'partner_offers', 'partner_businesses', 'media_files',
            'gallery_photos', 'email_unsubscribes', 'announcements', 'registration_applications',
            'rental_bookings', 'rental_settings', 'size_charts', 'clothing_packages', 'stock_movements',
            'warehouse_products', 'payment_settlements', 'online_checkouts', 'discount_reasons',
            'closed_finance_months', 'budget_lines', 'expenses', 'revenues', 'cash_accounts',
            'receipt_issues', 'receipt_series', 'athlete_transactions', 'fee_charge_templates',
            'progress_reports', 'matches', 'attendances', 'trainings', 'schedule_slots',
            'athlete_change_logs', 'parent_athlete_links', 'academy_class_athlete', 'athletes',
            'academy_classes', 'club_members', 'staff_members', 'coaches', 'associations',
            'club_seasons', 'sports', 'facilities',
        ];

        foreach ($tables as $table) {
            Schema::dropIfExists($table);
        }
    }
};
