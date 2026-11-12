<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_business_role CHECK (role IN ('customer','technician','admin'))");
        DB::statement('ALTER TABLE reviews ADD CONSTRAINT reviews_rating_range CHECK (rating BETWEEN 1 AND 5)');
        DB::statement('ALTER TABLE repair_estimates ADD CONSTRAINT positive_estimate CHECK (amount > 0)');
        DB::statement('ALTER TABLE appointments ADD CONSTRAINT ordered_appointment CHECK (ends_at > starts_at)');
        DB::statement("ALTER TABLE technician_profiles ADD CONSTRAINT technician_verification_values CHECK (verification_status IN ('pending','approved','rejected'))");
        DB::statement("ALTER TABLE technician_profiles ADD CONSTRAINT technician_availability_values CHECK (availability IN ('available','busy','unavailable'))");
        DB::statement("ALTER TABLE repair_requests ADD CONSTRAINT repair_status_values CHECK (status IN ('pending','accepted','scheduled','diagnosing','awaiting_customer_approval','in_repair','ready_for_pickup','completed','canceled','rejected'))");
    }

    public function down(): void
    {
        foreach (['users' => 'users_business_role', 'reviews' => 'reviews_rating_range', 'repair_estimates' => 'positive_estimate', 'appointments' => 'ordered_appointment', 'technician_profiles' => 'technician_verification_values', 'repair_requests' => 'repair_status_values'] as $table => $constraint) {
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT {$constraint}");
        }
        DB::statement('ALTER TABLE technician_profiles DROP CONSTRAINT technician_availability_values');
    }
};
