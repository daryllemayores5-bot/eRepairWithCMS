<?php

namespace App\Services;

use Illuminate\Database\Connection;

class DatabaseIntegrity
{
    public function checks(Connection $connection): array
    {
        $required = ['payment_subscription_owner_fk', 'review_repair_owners_fk', 'users_account_status_valid',
            'appointments_status_valid', 'repair_estimates_state_valid', 'technician_experience_valid',
            'subscription_valid', 'demo_payment_valid', 'subscription_plan_valid', 'users_business_role', 'reviews_rating_range'];
        $validated = $connection->select("SELECT c.conname FROM pg_constraint c JOIN pg_namespace n ON n.oid = c.connamespace WHERE n.nspname = 'public' AND c.convalidated");
        $missing = array_diff($required, array_column($validated, 'conname'));
        $unindexed = $connection->select(<<<'SQL'
            SELECT c.conname
            FROM pg_constraint c
            JOIN pg_namespace n ON n.oid = c.connamespace
            WHERE c.contype = 'f' AND n.nspname = 'public'
              AND NOT EXISTS (
                SELECT 1 FROM pg_index i
                WHERE i.indrelid = c.conrelid AND i.indisvalid AND i.indisready
                  AND i.indpred IS NULL AND i.indkey[0] = c.conkey[1]
              )
            SQL);
        $uniqueIndexes = ['subscription_one_active', 'subscription_one_pending', 'subscription_one_scheduled', 'demo_payment_one_pending'];
        $validIndexes = $connection->select("SELECT ci.relname FROM pg_index i JOIN pg_class ci ON ci.oid = i.indexrelid JOIN pg_namespace n ON n.oid = ci.relnamespace WHERE n.nspname = 'public' AND i.indisvalid AND i.indisready AND i.indisunique");
        $missingUnique = array_diff($uniqueIndexes, array_column($validIndexes, 'relname'));

        return [
            ['key' => 'database_constraints', 'ready' => $missing === [], 'detail' => 'Validated ownership, state, monetary and subscription constraints.'.($missing ? ' Missing: '.implode(', ', $missing) : '')],
            ['key' => 'database_fk_indexes', 'ready' => $unindexed === [], 'detail' => 'Every foreign key has a usable nonpartial index beginning with its first referencing column.'.($unindexed ? ' Missing for: '.implode(', ', array_column($unindexed, 'conname')) : '')],
            ['key' => 'database_subscription_uniqueness', 'ready' => $missingUnique === [], 'detail' => 'Unique indexes prevent duplicate pending requests, active subscriptions and scheduled plan changes.'],
        ];
    }
}
