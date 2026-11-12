<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug')->unique();
            $table->text('description');
            $table->decimal('price', 10, 2);
            $table->unsignedInteger('duration_days')->default(30);
            $table->json('features');
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });
        Schema::create('technician_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('technician_id')->constrained('technician_profiles')->restrictOnDelete();
            $table->foreignId('subscription_plan_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('pending');
            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('expires_at')->nullable()->index();
            $table->timestampsTz();
            $table->index(['technician_id', 'status']);
        });
        Schema::create('subscription_demo_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('technician_id')->constrained('technician_profiles')->restrictOnDelete();
            $table->foreignId('technician_subscription_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->unsignedInteger('duration_days');
            $table->string('plan_name', 100);
            $table->string('transaction_reference', 64)->unique();
            $table->uuid('idempotency_key');
            $table->string('payment_method', 30)->default('demo_simulation');
            $table->string('status', 20)->default('pending')->index();
            $table->boolean('is_demo')->default(true);
            $table->boolean('is_seeded_demo')->default(false);
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('approved_at')->nullable()->index();
            $table->timestampTz('rejected_at')->nullable();
            $table->timestampsTz();
            $table->unique(['technician_id', 'idempotency_key']);
        });
        // PostgreSQL is already the application's required database. These guards
        // also protect against concurrent requests outside the normal service path.
        DB::statement("CREATE UNIQUE INDEX subscription_one_active ON technician_subscriptions (technician_id) WHERE status = 'active'");
        DB::statement("CREATE UNIQUE INDEX subscription_one_pending ON technician_subscriptions (technician_id) WHERE status = 'pending'");
        DB::statement("CREATE UNIQUE INDEX demo_payment_one_pending ON subscription_demo_payments (technician_id) WHERE status = 'pending'");
        DB::statement("ALTER TABLE subscription_plans ADD CONSTRAINT subscription_plan_valid CHECK (price >= 0 AND duration_days BETWEEN 1 AND 365 AND (slug <> 'free' OR price = 0))");
        DB::statement("ALTER TABLE technician_subscriptions ADD CONSTRAINT subscription_valid CHECK (status IN ('pending','active','expired','cancelled') AND (status <> 'active' OR (starts_at IS NOT NULL AND expires_at > starts_at)))");
        DB::statement("ALTER TABLE subscription_demo_payments ADD CONSTRAINT demo_payment_valid CHECK (is_demo = true AND payment_method = 'demo_simulation' AND amount >= 0 AND duration_days BETWEEN 1 AND 365 AND status IN ('pending','approved','rejected') AND ((status = 'pending' AND approved_at IS NULL AND approved_by IS NULL AND rejected_at IS NULL) OR (status = 'approved' AND approved_at IS NOT NULL AND approved_by IS NOT NULL AND rejected_at IS NULL) OR (status = 'rejected' AND rejected_at IS NOT NULL AND approved_at IS NULL AND approved_by IS NULL)))");
        Schema::create('technician_profile_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('technician_id')->constrained('technician_profiles')->cascadeOnDelete();
            $table->date('viewed_on');
            $table->char('visitor_hash', 64);
            $table->unique(['technician_id', 'viewed_on', 'visitor_hash']);
            $table->index(['technician_id', 'viewed_on']);
        });
        $now = now();
        DB::table('subscription_plans')->insert([
            ['name' => 'Free', 'slug' => 'free', 'description' => 'Find customers and manage repairs for free.', 'price' => '0.00', 'duration_days' => 30, 'features' => json_encode(['Basic profile', 'Standard finder listing', 'Customer repair inquiries', 'Basic dashboard', 'Existing repair management']), 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Pro Technician', 'slug' => 'pro', 'description' => 'Optional subscription benefits. DEMO payments only; no real money is processed.', 'price' => '199.00', 'duration_days' => 30, 'features' => json_encode(['All Free features', 'Featured listing', 'Priority among equally relevant results', 'Pro subscription badge (not verification)', 'Profile view and inquiry analytics', 'Renewal without losing remaining time']), 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('technician_profile_views');
        Schema::dropIfExists('subscription_demo_payments');
        Schema::dropIfExists('technician_subscriptions');
        Schema::dropIfExists('subscription_plans');
    }
};
