<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEXES = [
        'repair_categories' => ['db_categories_device' => ['device_type_id'], 'db_categories_specialization' => ['specialization_id']],
        'technician_profiles' => ['db_profiles_verifier' => ['verified_by']],
        'technician_device_types' => ['db_devices_reverse' => ['device_type_id', 'technician_profile_id']],
        'technician_specializations' => ['db_specializations_reverse' => ['specialization_id', 'technician_profile_id']],
        'repair_requests' => ['db_requests_device' => ['device_type_id'], 'db_requests_brand' => ['device_brand_id'], 'db_requests_category' => ['repair_category_id']],
        'repair_attachments' => ['db_attachments_repair' => ['repair_request_id', 'id'], 'db_attachments_uploader' => ['uploaded_by']],
        'repair_status_histories' => ['db_history_repair' => ['repair_request_id', 'id'], 'db_history_actor' => ['updated_by']],
        'repair_estimates' => ['db_estimates_repair' => ['repair_request_id', 'id']],
        'appointments' => ['db_appointments_repair' => ['repair_request_id', 'status', 'starts_at']],
        'reviews' => ['db_reviews_technician' => ['technician_profile_id', 'hidden'], 'db_reviews_customer' => ['customer_id']],
        'activity_logs' => ['db_activity_actor' => ['actor_id']],
        'technician_subscriptions' => ['db_subscriptions_plan' => ['subscription_plan_id']],
        'subscription_demo_payments' => ['db_payments_approver' => ['approved_by']],
    ];

    public function up(): void
    {
        // PostgreSQL DDL is transactional. Invalid legacy rows stop the upgrade
        // without silently rewriting records or leaving a partially applied schema.
        DB::statement("SET LOCAL lock_timeout = '5s'");
        foreach (self::INDEXES as $table => $indexes) {
            Schema::table($table, function (Blueprint $blueprint) use ($indexes) {
                foreach ($indexes as $name => $columns) {
                    $blueprint->index($columns, $name);
                }
            });
        }
        Schema::table('technician_subscriptions', fn (Blueprint $table) => $table->unique(['id', 'technician_id'], 'db_subscription_owner_key'));
        Schema::table('repair_requests', fn (Blueprint $table) => $table->unique(['id', 'customer_id', 'technician_profile_id'], 'db_repair_owners_key'));
        DB::statement('ALTER TABLE subscription_demo_payments ADD CONSTRAINT payment_subscription_owner_fk FOREIGN KEY (technician_subscription_id, technician_id) REFERENCES technician_subscriptions (id, technician_id) ON DELETE CASCADE');
        DB::statement('ALTER TABLE reviews ADD CONSTRAINT review_repair_owners_fk FOREIGN KEY (repair_request_id, customer_id, technician_profile_id) REFERENCES repair_requests (id, customer_id, technician_profile_id) ON DELETE RESTRICT');
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_account_status_valid CHECK (account_status IN ('active', 'suspended'))");
        DB::statement("ALTER TABLE appointments ADD CONSTRAINT appointments_status_valid CHECK (status IN ('proposed', 'confirmed', 'completed', 'canceled'))");
        DB::statement("ALTER TABLE repair_estimates ADD CONSTRAINT repair_estimates_state_valid CHECK (status IN ('pending', 'approved', 'rejected', 'superseded') AND currency = 'PHP')");
        DB::statement('ALTER TABLE technician_profiles ADD CONSTRAINT technician_experience_valid CHECK (years_experience BETWEEN 0 AND 60)');
        DB::statement('ALTER TABLE technician_subscriptions DROP CONSTRAINT subscription_valid');
        DB::statement("ALTER TABLE technician_subscriptions ADD CONSTRAINT subscription_valid CHECK (status IN ('pending','active','expired','cancelled') AND (status <> 'active' OR (starts_at IS NOT NULL AND expires_at IS NOT NULL AND expires_at > starts_at)))");
    }

    public function down(): void
    {
        DB::statement("SET LOCAL lock_timeout = '5s'");
        DB::statement('ALTER TABLE reviews DROP CONSTRAINT review_repair_owners_fk');
        DB::statement('ALTER TABLE subscription_demo_payments DROP CONSTRAINT payment_subscription_owner_fk');
        Schema::table('repair_requests', fn (Blueprint $table) => $table->dropUnique('db_repair_owners_key'));
        Schema::table('technician_subscriptions', fn (Blueprint $table) => $table->dropUnique('db_subscription_owner_key'));
        foreach (['users' => 'users_account_status_valid', 'appointments' => 'appointments_status_valid', 'repair_estimates' => 'repair_estimates_state_valid', 'technician_profiles' => 'technician_experience_valid'] as $table => $name) {
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT {$name}");
        }
        DB::statement('ALTER TABLE technician_subscriptions DROP CONSTRAINT subscription_valid');
        DB::statement("ALTER TABLE technician_subscriptions ADD CONSTRAINT subscription_valid CHECK (status IN ('pending','active','expired','cancelled') AND (status <> 'active' OR (starts_at IS NOT NULL AND expires_at > starts_at)))");
        foreach (self::INDEXES as $table => $indexes) {
            Schema::table($table, function (Blueprint $blueprint) use ($indexes) {
                foreach (array_keys($indexes) as $name) {
                    $blueprint->dropIndex($name);
                }
            });
        }
    }
};
