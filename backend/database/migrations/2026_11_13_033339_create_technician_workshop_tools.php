<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('note_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('technician_id')->constrained('technician_profiles')->restrictOnDelete();
            $table->string('title', 100);
            $table->text('body');
            $table->timestampsTz();
            $table->unique(['technician_id', 'title']);
        });
        Schema::create('inventory_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('technician_id')->constrained('technician_profiles')->restrictOnDelete();
            $table->string('sku', 64);
            $table->string('name', 120);
            $table->integer('quantity')->default(0);
            $table->integer('reorder_level')->default(0);
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->boolean('active')->default(true);
            $table->timestampsTz();
            $table->unique(['technician_id', 'sku']);
            $table->unique(['id', 'technician_id'], 'inventory_part_owner_key');
        });
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('technician_id')->constrained('technician_profiles')->restrictOnDelete();
            $table->foreignId('inventory_part_id');
            $table->foreignId('repair_request_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('kind', 16);
            $table->integer('quantity');
            $table->decimal('unit_cost', 12, 2);
            $table->string('note', 500)->nullable();
            $table->uuid('idempotency_key');
            $table->timestampsTz();
            $table->unique(['technician_id', 'idempotency_key']);
            $table->foreign(['inventory_part_id', 'technician_id'], 'inventory_movement_part_owner_fk')
                ->references(['id', 'technician_id'])->on('inventory_parts')->restrictOnDelete();
            $table->index(['technician_id', 'created_at']);
            $table->index('repair_request_id');
            $table->index('inventory_part_id');
            $table->index('actor_id');
        });
        DB::statement('ALTER TABLE inventory_parts ADD CONSTRAINT inventory_parts_nonnegative CHECK (quantity >= 0 AND reorder_level >= 0 AND unit_cost >= 0)');
        DB::statement("ALTER TABLE inventory_movements ADD CONSTRAINT inventory_movements_valid CHECK (quantity > 0 AND unit_cost >= 0 AND ((kind = 'usage' AND repair_request_id IS NOT NULL) OR (kind IN ('opening','restock') AND repair_request_id IS NULL)))");
        $features = [
            'pro' => ['Downloadable PDF quotations and job sheets', 'Reusable repair-note templates'],
            'premium' => ['All Pro document and template tools', 'Parts inventory and repair-linked stock deductions', 'Low-stock alerts', 'Downloadable business reports (CSV and PDF)'],
        ];
        foreach ($features as $slug => $benefits) {
            $plan = DB::table('subscription_plans')->where('slug', $slug)->first();
            if ($plan) {
                $existing = json_decode($plan->features, true, flags: JSON_THROW_ON_ERROR);
                DB::table('subscription_plans')->where('id', $plan->id)->update(['features' => json_encode(array_values(array_unique([...$existing, ...$benefits])), JSON_THROW_ON_ERROR)]);
            }
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Workshop records preserve stock and repair history. Use an explicitly reviewed recovery migration instead of dropping these tables.');
    }
};
