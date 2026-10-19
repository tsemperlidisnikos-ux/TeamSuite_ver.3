<?php

$models = [
    'Sport' => ['fillable' => ['club_id','name','legacy_id','meta'], 'casts' => ['meta' => 'array']],
    'ClubSeason' => ['fillable' => ['club_id','name','starts_on','ends_on','is_active','legacy_id'], 'casts' => ['starts_on' => 'date','ends_on' => 'date','is_active' => 'boolean'], 'table' => 'club_seasons'],
    'Association' => ['fillable' => ['club_id','name','legacy_id','meta'], 'casts' => ['meta' => 'array']],
    'Coach' => ['fillable' => ['club_id','user_id','first_name','last_name','email','phone','sport','hire_date','is_active','gga_code','license_level','legacy_id','meta'], 'casts' => ['hire_date' => 'date','is_active' => 'boolean','meta' => 'array']],
    'StaffMember' => ['fillable' => ['club_id','user_id','first_name','last_name','role_title','email','phone','is_active','legacy_id','meta'], 'casts' => ['is_active' => 'boolean','meta' => 'array'], 'table' => 'staff_members'],
    'ClubMember' => ['fillable' => ['club_id','first_name','last_name','email','phone','member_number','status','legacy_id','meta'], 'casts' => ['meta' => 'array'], 'table' => 'club_members'],
    'AcademyClass' => ['fillable' => ['club_id','coach_id','club_season_id','name','sport','age_group','gender','max_students','monthly_fee','schedule_summary','legacy_id','meta'], 'casts' => ['monthly_fee' => 'decimal:2','meta' => 'array'], 'table' => 'academy_classes'],
    'Athlete' => ['fillable' => ['club_id','first_name','last_name','email','phone','birth_date','status','monthly_fee','enrolled_at','amka_encrypted','amka_fingerprint','gender','guardian_name','guardian_phone','sport','health_card','health_card_expires','gdpr_consent','legacy_id','meta'], 'casts' => ['birth_date' => 'date','enrolled_at' => 'date','monthly_fee' => 'decimal:2','health_card' => 'boolean','health_card_expires' => 'date','amka_encrypted' => 'encrypted','meta' => 'array']],
    'ParentAthleteLink' => ['fillable' => ['club_id','parent_user_id','athlete_id','relation','receives_push','receives_sms','legacy_id'], 'casts' => ['receives_push' => 'boolean','receives_sms' => 'boolean'], 'table' => 'parent_athlete_links'],
    'AthleteChangeLog' => ['fillable' => ['club_id','athlete_id','user_id','field','old_value','new_value','legacy_id'], 'table' => 'athlete_change_logs', 'soft' => false],
    'Facility' => ['fillable' => ['club_id','name','address','is_rentable','legacy_id','meta'], 'casts' => ['is_rentable' => 'boolean','meta' => 'array']],
    'ScheduleSlot' => ['fillable' => ['club_id','academy_class_id','facility_id','weekday','starts_at','ends_at','title','legacy_id','meta'], 'casts' => ['meta' => 'array'], 'table' => 'schedule_slots'],
    'Training' => ['fillable' => ['club_id','academy_class_id','coach_id','facility_id','starts_at','ends_at','title','status','notes','legacy_id','meta'], 'casts' => ['starts_at' => 'datetime','ends_at' => 'datetime','meta' => 'array']],
    'Attendance' => ['fillable' => ['club_id','training_id','athlete_id','status','attended_on','legacy_id','meta'], 'casts' => ['attended_on' => 'date','meta' => 'array']],
    'SportMatch' => ['fillable' => ['club_id','academy_class_id','starts_at','opponent','venue','home_away','score_for','score_against','status','legacy_id','meta'], 'casts' => ['starts_at' => 'datetime','meta' => 'array'], 'table' => 'matches', 'class' => 'SportMatch'],
    'ProgressReport' => ['fillable' => ['club_id','athlete_id','coach_id','report_date','body','legacy_id','meta'], 'casts' => ['report_date' => 'date','meta' => 'array']],
    'FeeChargeTemplate' => ['fillable' => ['club_id','name','amount','applies_to','auto_generate','is_active','legacy_id','meta'], 'casts' => ['amount' => 'decimal:2','auto_generate' => 'boolean','is_active' => 'boolean','meta' => 'array']],
    'AthleteTransaction' => ['fillable' => ['club_id','athlete_id','type','amount','month','year','payment_method','receipt_number','receipt_series','receipt_seq','allocates_charge_id','comments','legacy_id','meta'], 'casts' => ['amount' => 'decimal:2','meta' => 'array']],
    'ReceiptSeries' => ['fillable' => ['club_id','series','range_from','range_to','next_number','is_active','legacy_id'], 'casts' => ['is_active' => 'boolean'], 'table' => 'receipt_series'],
    'ReceiptIssue' => ['fillable' => ['club_id','receipt_series_id','athlete_transaction_id','series','number','status','issued_at','cancelled_at','legacy_id','meta'], 'casts' => ['issued_at' => 'datetime','cancelled_at' => 'datetime','meta' => 'array'], 'soft' => false],
    'CashAccount' => ['fillable' => ['club_id','name','opening_balance','is_active','legacy_id'], 'casts' => ['opening_balance' => 'decimal:2','is_active' => 'boolean']],
    'Revenue' => ['fillable' => ['club_id','cash_account_id','created_by','amount','category','occurred_on','description','legacy_id','meta'], 'casts' => ['amount' => 'decimal:2','occurred_on' => 'date','meta' => 'array']],
    'Expense' => ['fillable' => ['club_id','cash_account_id','created_by','amount','category','occurred_on','description','legacy_id','meta'], 'casts' => ['amount' => 'decimal:2','occurred_on' => 'date','meta' => 'array']],
    'BudgetLine' => ['fillable' => ['club_id','label','year','amount','legacy_id','meta'], 'casts' => ['amount' => 'decimal:2','meta' => 'array']],
    'ClosedFinanceMonth' => ['fillable' => ['club_id','year_month','closed_by','closed_at'], 'casts' => ['closed_at' => 'datetime'], 'soft' => false, 'uuid' => false],
    'DiscountReason' => ['fillable' => ['club_id','name','sport','default_amount','legacy_id'], 'casts' => ['default_amount' => 'decimal:2']],
    'OnlineCheckout' => ['fillable' => ['club_id','athlete_id','provider','order_code','amount','status','idempotency_key','payload','legacy_id'], 'casts' => ['amount' => 'decimal:2','payload' => 'array']],
    'PaymentSettlement' => ['fillable' => ['club_id','online_checkout_id','provider','external_id','idempotency_key','amount','status','payload'], 'casts' => ['amount' => 'decimal:2','payload' => 'array'], 'soft' => false],
    'WarehouseProduct' => ['fillable' => ['club_id','name','sku','stock_qty','low_stock_threshold','unit_price','legacy_id','meta'], 'casts' => ['unit_price' => 'decimal:2','meta' => 'array']],
    'StockMovement' => ['fillable' => ['club_id','warehouse_product_id','quantity','reason','legacy_id'], 'casts' => []],
    'ClothingPackage' => ['fillable' => ['club_id','name','price','legacy_id','meta'], 'casts' => ['price' => 'decimal:2','meta' => 'array']],
    'SizeChart' => ['fillable' => ['club_id','chart'], 'casts' => ['chart' => 'array'], 'soft' => false],
    'RentalSetting' => ['fillable' => ['club_id','public_enabled','rules'], 'casts' => ['public_enabled' => 'boolean','rules' => 'array'], 'soft' => false],
    'RentalBooking' => ['fillable' => ['club_id','facility_id','customer_name','customer_email','customer_phone','starts_at','ends_at','amount','status','legacy_id','meta'], 'casts' => ['starts_at' => 'datetime','ends_at' => 'datetime','amount' => 'decimal:2','meta' => 'array']],
    'RegistrationApplication' => ['fillable' => ['club_id','type','status','first_name','last_name','email','phone','form_snapshot','gdpr_accepted','signature_path','legacy_id'], 'casts' => ['form_snapshot' => 'array','gdpr_accepted' => 'boolean']],
    'Announcement' => ['fillable' => ['club_id','created_by','title','body','audience','published_at','legacy_id','meta'], 'casts' => ['audience' => 'array','published_at' => 'datetime','meta' => 'array']],
    'EmailUnsubscribe' => ['fillable' => ['club_id','email'], 'soft' => false, 'uuid' => false],
    'GalleryPhoto' => ['fillable' => ['club_id','title','path','gdpr_cleared','legacy_id','meta'], 'casts' => ['gdpr_cleared' => 'boolean','meta' => 'array']],
    'MediaFile' => ['fillable' => ['club_id','disk','path','kind','original_name'], 'soft' => false],
    'PartnerBusiness' => ['fillable' => ['club_id','name','contact','legacy_id','meta'], 'casts' => ['meta' => 'array']],
    'PartnerOffer' => ['fillable' => ['club_id','partner_business_id','title','description','legacy_id'], 'casts' => []],
    'DocumentProtocolEntry' => ['fillable' => ['club_id','direction','protocol_number','subject','document_date','legacy_id','meta'], 'casts' => ['document_date' => 'date','meta' => 'array']],
    'AmkaAccessLog' => ['fillable' => ['club_id','athlete_id','user_id','action','legacy_id'], 'soft' => false],
    'GdprAuditLog' => ['fillable' => ['club_id','user_id','action','meta','legacy_id'], 'casts' => ['meta' => 'array'], 'soft' => false],
    'SuppressedFeeChargeKey' => ['fillable' => ['club_id','suppress_key'], 'soft' => false, 'uuid' => false],
    'PlatformWaitlist' => ['fillable' => ['club_name','contact_name','email','phone','status','notes'], 'club' => false, 'soft' => false],
];

$dir = __DIR__ . '/../app/Models';

foreach ($models as $name => $cfg) {
    $class = $cfg['class'] ?? $name;
    $table = $cfg['table'] ?? null;
    $soft = $cfg['soft'] ?? true;
    $uuid = $cfg['uuid'] ?? true;
    $club = $cfg['club'] ?? true;
    $fillable = $cfg['fillable'] ?? [];
    $casts = $cfg['casts'] ?? [];

    $uses = ["Illuminate\\Database\\Eloquent\\Model"];
    $traits = [];
    if ($uuid) {
        $uses[] = "Illuminate\\Database\\Eloquent\\Concerns\\HasUuids";
        $traits[] = 'HasUuids';
    }
    if ($soft) {
        $uses[] = "Illuminate\\Database\\Eloquent\\SoftDeletes";
        $traits[] = 'SoftDeletes';
    }
    if ($club) {
        $uses[] = "App\\Models\\Concerns\\BelongsToClub";
        $traits[] = 'BelongsToClub';
    }

    $useStmt = implode("\n", array_map(fn ($u) => "use $u;", array_unique($uses)));
    $traitStmt = $traits ? '    use ' . implode(', ', $traits) . ";\n" : '';
    $tableStmt = $table ? "\n    protected \$table = '{$table}';\n" : "\n";
    $fill = implode("',\n        '", $fillable);
    $castLines = [];
    foreach ($casts as $k => $v) {
        $castLines[] = "            '{$k}' => '{$v}',";
    }
    $castBlock = $castLines
        ? "\n    protected function casts(): array\n    {\n        return [\n" . implode("\n", $castLines) . "\n        ];\n    }\n"
        : '';

    $content = "<?php\n\nnamespace App\\Models;\n\n{$useStmt}\n\nclass {$class} extends Model\n{\n{$traitStmt}{$tableStmt}\n    protected \$fillable = [\n        '{$fill}',\n    ];\n{$castBlock}}\n";

    file_put_contents("{$dir}/{$class}.php", $content);
    echo "Wrote {$class}\n";
}
