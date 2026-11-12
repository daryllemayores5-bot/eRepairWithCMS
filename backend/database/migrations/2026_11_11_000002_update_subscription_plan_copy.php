<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const OLD_DESCRIPTION = 'Optional subscription benefits. DEMO payments only; no real money is processed.';

    private const NEW_DESCRIPTION = 'Reach more customers with featured visibility, a Pro subscription badge and profile analytics.';

    public function up(): void
    {
        DB::table('subscription_plans')->where('slug', 'pro')->where('description', self::OLD_DESCRIPTION)
            ->update(['description' => self::NEW_DESCRIPTION, 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('subscription_plans')->where('slug', 'pro')->where('description', self::NEW_DESCRIPTION)
            ->update(['description' => self::OLD_DESCRIPTION, 'updated_at' => now()]);
    }
};
