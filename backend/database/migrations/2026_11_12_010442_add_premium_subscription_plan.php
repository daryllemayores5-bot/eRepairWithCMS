<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("SET LOCAL lock_timeout = '5s'");
        DB::statement('ALTER TABLE technician_subscriptions DROP CONSTRAINT IF EXISTS subscription_valid');
        DB::statement("ALTER TABLE technician_subscriptions ADD CONSTRAINT subscription_valid CHECK (status IN ('pending','active','scheduled','expired','cancelled') AND (status NOT IN ('active','scheduled') OR (starts_at IS NOT NULL AND expires_at IS NOT NULL AND expires_at > starts_at)))");
        DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS subscription_one_scheduled ON technician_subscriptions (technician_id) WHERE status = 'scheduled'");
        DB::table('subscription_plans')->insertOrIgnore([
            'slug' => 'premium', 'name' => 'Premium Technician', 'price' => '399.00',
            'duration_days' => 30, 'is_active' => true,
            'description' => 'All Pro benefits, enhanced featured visibility and detailed profile engagement statistics. DEMO payments only.',
            'features' => json_encode(['All Free and Pro features', 'Premium Technician subscription badge (not verification)', 'Featured technician placement', 'Higher promotional visibility than Pro among equally relevant results', 'Advanced profile analytics', 'Profile views and inquiries over time', '7, 30 and 90-day analytics filters with previous-period statistics']),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new RuntimeException('This additive subscription migration is forward-only to preserve plan and payment history.');
    }
};
