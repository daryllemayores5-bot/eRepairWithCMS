<?php

namespace Tests\Feature;

use App\Models\DeviceBrand;
use App\Models\DeviceType;
use App\Models\InventoryPart;
use App\Models\NoteTemplate;
use App\Models\RepairCategory;
use App\Models\RepairRequest;
use App\Models\SubscriptionPlan;
use App\Models\TechnicianProfile;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class WorkshopToolsTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertDisposablePostgresDatabase();
    }

    protected function setUp(): void
    {
        if (getenv('RUN_POSTGRES_TESTS') !== '1') {
            $this->markTestSkipped('Requires the guarded disposable PostgreSQL database.');
        }
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 13)->setTime(12, 0));
        config(['sanctum.stateful' => ['127.0.0.1:5173']]);
        $this->withHeader('Origin', 'http://127.0.0.1:5173');
    }

    private function upgrade(TechnicianProfile $profile, string $slug = 'premium')
    {
        $admin = User::where('role', 'admin')->first() ?? User::factory()->create(['role' => 'admin', 'super' => true]);
        $service = app(SubscriptionService::class);
        $payment = $service->requestPlan($profile->user, (string) Str::uuid(), SubscriptionPlan::where('slug', $slug)->firstOrFail()->price, $slug);

        return $service->decide($admin, $payment, 'approved');
    }

    private function repair(TechnicianProfile $profile, string $status = 'in_repair', array $extra = []): RepairRequest
    {
        $type = DeviceType::firstOrCreate(['slug' => 'workshop-laptop'], ['name' => 'Laptop']);
        $brand = DeviceBrand::firstOrCreate(['slug' => 'workshop-brand'], ['name' => 'Workshop brand']);
        $category = RepairCategory::firstOrCreate(['slug' => 'workshop-screen'], ['name' => 'Screen repair']);
        $repair = new RepairRequest;
        $repair->forceFill(array_merge([
            'reference' => 'TEST-'.Str::ulid(), 'customer_id' => User::factory()->create()->id,
            'technician_profile_id' => $profile->id, 'device_type_id' => $type->id,
            'device_brand_id' => $brand->id, 'repair_category_id' => $category->id,
            'model' => 'Test laptop', 'problem_description' => 'Screen needs replacement.',
            'status' => $status,
        ], $extra))->save();

        return $repair;
    }

    private function movement(string $kind, int $quantity, array $extra = []): array
    {
        return array_merge(['kind' => $kind, 'quantity' => $quantity, 'idempotency_key' => (string) Str::uuid()], $extra);
    }

    public function test_guest_customer_and_free_technician_cannot_use_paid_tools(): void
    {
        $this->getJson('/api/v1/technician/workshop')->assertUnauthorized();
        $this->actingAs(User::factory()->create())->getJson('/api/v1/technician/workshop')->assertForbidden();
        $profile = TechnicianProfile::factory()->create();
        $this->actingAs($profile->user)->getJson('/api/v1/technician/workshop')
            ->assertOk()->assertJsonPath('data.tier', 'free')->assertJsonPath('data.documents', false)->assertJsonPath('data.inventory', false);
        foreach (['note-templates', 'workshop/repairs', 'inventory', 'inventory-movements', 'business-reports'] as $path) {
            $this->getJson('/api/v1/technician/'.$path)->assertForbidden();
        }
        $this->postJson('/api/v1/technician/note-templates', ['title' => 'Not allowed', 'body' => 'Not saved'])->assertForbidden();
        $this->assertDatabaseCount('note_templates', 0);
    }

    public function test_pro_creates_edits_and_deletes_only_own_reusable_templates(): void
    {
        $profile = TechnicianProfile::factory()->create();
        $this->upgrade($profile, 'pro');
        $other = NoteTemplate::factory()->create(['title' => 'Private template']);
        $response = $this->actingAs($profile->user)->postJson('/api/v1/technician/note-templates', ['title' => 'Pickup', 'body' => 'Device tested. Ready for pickup.', 'technician_id' => $other->technician_id])->assertCreated();
        $id = $response->json('data.id');
        $this->assertDatabaseHas('note_templates', ['id' => $id, 'technician_id' => $profile->id]);
        $this->getJson('/api/v1/technician/note-templates')->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Pickup');
        $this->putJson('/api/v1/technician/note-templates/'.$id, ['title' => 'Pickup instructions', 'body' => 'Please bring your repair reference.'])->assertOk();
        $this->assertDatabaseHas('note_templates', ['id' => $id, 'body' => 'Please bring your repair reference.']);
        $this->putJson('/api/v1/technician/note-templates/'.$other->id, ['title' => 'Stolen', 'body' => 'Changed'])->assertNotFound();
        $this->deleteJson('/api/v1/technician/note-templates/'.$other->id)->assertNotFound();
        $this->deleteJson('/api/v1/technician/note-templates/'.$id)->assertNoContent();
        $this->assertDatabaseMissing('note_templates', ['id' => $id]);
        $this->assertModelExists($other);
        $this->getJson('/api/v1/technician/inventory')->assertForbidden();
        $this->getJson('/api/v1/technician/business-reports')->assertForbidden();
    }

    public function test_template_validation_matches_the_repair_note_limit_and_prevents_duplicate_titles(): void
    {
        $profile = TechnicianProfile::factory()->create();
        $this->upgrade($profile, 'pro');
        NoteTemplate::factory()->create(['technician_id' => $profile->id, 'title' => 'Diagnosis']);
        $this->actingAs($profile->user)->postJson('/api/v1/technician/note-templates', ['title' => 'Diagnosis', 'body' => str_repeat('x', 2001)])
            ->assertUnprocessable()->assertJsonValidationErrors(['title', 'body']);
        $this->assertDatabaseCount('note_templates', 1);
    }

    public function test_pro_downloads_real_private_pdf_quotations_and_job_sheets_for_own_repairs(): void
    {
        $profile = TechnicianProfile::factory()->create();
        $this->upgrade($profile, 'pro');
        $repair = $this->repair($profile);
        $repair->estimates()->create(['amount' => '2100.50', 'description' => 'Display and labor', 'status' => 'approved']);
        $repair->history()->create(['new_status' => 'in_repair', 'updated_by' => $profile->user_id, 'remarks' => 'Diagnosis complete.']);
        $url = '/api/v1/technician/workshop/repairs/'.$repair->id.'/documents/';
        $quotation = $this->actingAs($profile->user)->get($url.'quotation')->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertHeader('Cache-Control', 'no-store, private');
        $this->assertStringStartsWith('%PDF-1.4', $quotation->getContent());
        $this->assertStringContainsString('PHP 2100.50', $quotation->getContent());
        $job = $this->get($url.'job-sheet')->assertOk();
        $this->assertStringContainsString('Diagnosis complete.', $job->getContent());
        $this->assertStringContainsString('Customer acknowledgement', $job->getContent());
        $this->get('/api/v1/technician/workshop/repairs')->assertJsonPath('data.data.0.id', $repair->id);
        $other = TechnicianProfile::factory()->create();
        $this->upgrade($other, 'pro');
        $this->actingAs($other->user)->get($url.'quotation', ['Accept' => 'application/json'])->assertNotFound();
        $this->actingAs($repair->customer)->get($url.'job-sheet', ['Accept' => 'application/json'])->assertForbidden();
    }

    public function test_quotation_requires_an_estimate_and_suspended_users_cannot_download(): void
    {
        $profile = TechnicianProfile::factory()->create();
        $this->upgrade($profile);
        $repair = $this->repair($profile);
        $this->actingAs($profile->user)->getJson('/api/v1/technician/workshop/repairs/'.$repair->id.'/documents/quotation')->assertUnprocessable();
        $profile->user->forceFill(['account_status' => 'suspended'])->save();
        $this->actingAs($profile->user->fresh())->getJson('/api/v1/technician/workshop')->assertForbidden();
    }

    public function test_premium_adds_stock_and_records_opening_quantity_with_owner_scoping(): void
    {
        $profile = TechnicianProfile::factory()->create();
        $this->upgrade($profile);
        $data = ['sku' => 'BAT-01', 'name' => 'Phone battery', 'quantity' => 5, 'reorder_level' => 2, 'unit_cost' => '400.25', 'idempotency_key' => (string) Str::uuid()];
        $response = $this->actingAs($profile->user)->postJson('/api/v1/technician/inventory', $data)->assertCreated()->assertJsonPath('data.quantity', 5);
        $this->assertDatabaseHas('inventory_parts', ['id' => $response->json('data.id'), 'technician_id' => $profile->id, 'quantity' => 5]);
        $this->assertDatabaseHas('inventory_movements', ['kind' => 'opening', 'quantity' => 5, 'unit_cost' => '400.25', 'technician_id' => $profile->id]);
        $this->postJson('/api/v1/technician/inventory', $data)->assertUnprocessable()->assertJsonValidationErrors('sku');
        $this->assertDatabaseCount('inventory_parts', 1);
        $other = InventoryPart::factory()->create(['name' => 'Other private stock']);
        $this->getJson('/api/v1/technician/inventory')->assertJsonCount(1, 'data.data');
        $this->patchJson('/api/v1/technician/inventory/'.$other->id, ['name' => 'Overwrite', 'reorder_level' => 0, 'active' => true])->assertNotFound();
    }

    public function test_parts_usage_is_repair_linked_idempotent_and_triggers_low_stock(): void
    {
        $profile = TechnicianProfile::factory()->create();
        $this->upgrade($profile);
        $part = InventoryPart::factory()->create(['technician_id' => $profile->id, 'quantity' => 5, 'reorder_level' => 2]);
        $repair = $this->repair($profile);
        $data = $this->movement('usage', 3, ['repair_request_id' => $repair->id]);
        $url = '/api/v1/technician/inventory/'.$part->id.'/movements';
        $this->actingAs($profile->user)->postJson($url, $data)->assertOk()->assertJsonPath('data.part.quantity', 2);
        $this->postJson($url, $data)->assertOk()->assertJsonPath('data.replayed', true)->assertJsonPath('data.part.quantity', 2);
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertDatabaseHas('inventory_movements', ['repair_request_id' => $repair->id, 'quantity' => 3, 'kind' => 'usage']);
        $this->getJson('/api/v1/technician/inventory?low_stock=1')->assertJsonPath('low_stock_count', 1)->assertJsonCount(1, 'data.data');
        $this->getJson('/api/v1/technician/inventory-movements')->assertJsonPath('data.data.0.repair.reference', $repair->reference);
        $this->postJson($url, [...$data, 'quantity' => 2])->assertUnprocessable()->assertJsonValidationErrors('idempotency_key');
        $this->assertSame(2, $part->fresh()->quantity);
    }

    public function test_stock_cannot_go_negative_or_be_used_on_other_or_closed_repairs(): void
    {
        $profile = TechnicianProfile::factory()->create();
        $this->upgrade($profile);
        $part = InventoryPart::factory()->create(['technician_id' => $profile->id, 'quantity' => 2]);
        $repair = $this->repair($profile);
        $foreign = $this->repair(TechnicianProfile::factory()->create());
        $closed = $this->repair($profile, 'completed', ['completed_at' => now()]);
        $url = '/api/v1/technician/inventory/'.$part->id.'/movements';
        $this->actingAs($profile->user)->postJson($url, $this->movement('usage', 3, ['repair_request_id' => $repair->id]))->assertUnprocessable()->assertJsonValidationErrors('quantity');
        $this->postJson($url, $this->movement('usage', 1, ['repair_request_id' => $foreign->id]))->assertNotFound();
        $this->postJson($url, $this->movement('usage', 1, ['repair_request_id' => $closed->id]))->assertUnprocessable()->assertJsonValidationErrors('repair_request_id');
        $this->assertSame(2, $part->fresh()->quantity);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_restock_preserves_historical_cost_and_quantity_cannot_be_patched(): void
    {
        $profile = TechnicianProfile::factory()->create();
        $this->upgrade($profile);
        $part = InventoryPart::factory()->create(['technician_id' => $profile->id, 'quantity' => 3, 'unit_cost' => '450.00']);
        $repair = $this->repair($profile);
        $url = '/api/v1/technician/inventory/'.$part->id;
        $this->actingAs($profile->user)->postJson($url.'/movements', $this->movement('usage', 1, ['repair_request_id' => $repair->id]))->assertOk();
        $this->postJson($url.'/movements', $this->movement('restock', 4, ['unit_cost' => '500.50']))->assertOk()->assertJsonPath('data.part.quantity', 6);
        $this->assertDatabaseHas('inventory_movements', ['kind' => 'usage', 'unit_cost' => '450.00']);
        $this->assertSame('500.50', $part->fresh()->unit_cost);
        $this->patchJson($url, ['name' => 'Battery', 'reorder_level' => 2, 'active' => true, 'quantity' => 999])->assertUnprocessable()->assertJsonValidationErrors('quantity');
        $this->patchJson($url, ['name' => 'Old battery', 'reorder_level' => 1, 'active' => false])->assertOk();
        $this->postJson($url.'/movements', $this->movement('restock', 1, ['unit_cost' => '1.00']))->assertUnprocessable()->assertJsonValidationErrors('part');
        $this->assertSame(6, $part->fresh()->quantity);
    }

    public function test_stock_validates_negative_and_fractional_quantities_without_writes(): void
    {
        $profile = TechnicianProfile::factory()->create();
        $this->upgrade($profile);
        $this->actingAs($profile->user)->postJson('/api/v1/technician/inventory', ['sku' => 'BAD SKU', 'name' => 'Test', 'quantity' => -1, 'reorder_level' => 0, 'unit_cost' => '0.001', 'idempotency_key' => 'bad'])
            ->assertUnprocessable()->assertJsonValidationErrors(['sku', 'quantity', 'unit_cost', 'idempotency_key']);
        $this->assertDatabaseCount('inventory_parts', 0);
    }

    public function test_expiration_removes_tool_access_without_deleting_saved_records(): void
    {
        $profile = TechnicianProfile::factory()->create();
        $payment = $this->upgrade($profile);
        $template = NoteTemplate::factory()->create(['technician_id' => $profile->id]);
        $part = InventoryPart::factory()->create(['technician_id' => $profile->id]);
        $this->travelTo($payment->subscription->expires_at);
        $this->actingAs($profile->user)->getJson('/api/v1/technician/workshop')->assertJsonPath('data.tier', 'free');
        $this->getJson('/api/v1/technician/note-templates')->assertForbidden();
        $this->getJson('/api/v1/technician/inventory')->assertForbidden();
        $this->assertModelExists($template);
        $this->assertModelExists($part);
    }

    public function test_scheduled_premium_tools_unlock_only_when_the_paid_period_starts(): void
    {
        $profile = TechnicianProfile::factory()->create();
        $pro = $this->upgrade($profile, 'pro');
        $premium = $this->upgrade($profile, 'premium');
        $this->actingAs($profile->user)->getJson('/api/v1/technician/workshop')->assertJsonPath('data.documents', true)->assertJsonPath('data.inventory', false);
        $this->getJson('/api/v1/technician/inventory')->assertForbidden();
        $this->travelTo($premium->subscription->starts_at);
        $this->getJson('/api/v1/technician/workshop')->assertJsonPath('data.tier', 'premium')->assertJsonPath('data.inventory', true);
        $this->getJson('/api/v1/technician/inventory')->assertOk();
    }

    public function test_reports_scope_owners_count_completion_dates_and_use_exact_costs(): void
    {
        $profile = TechnicianProfile::factory()->create();
        $this->upgrade($profile);
        $completed = $this->repair($profile, 'completed', ['created_at' => '2026-11-10 00:00:00+00', 'completed_at' => '2026-11-12 16:00:00+00']);
        $completed->estimates()->create(['amount' => '999.99', 'description' => 'Previous approved estimate', 'status' => 'approved']);
        $completed->estimates()->create(['amount' => '2100.50', 'description' => 'Latest approved estimate', 'status' => 'approved']);
        $this->repair($profile, 'pending', ['created_at' => '2026-11-12 16:00:00+00', 'model' => '=HYPERLINK("bad")']);
        $other = $this->repair(TechnicianProfile::factory()->create(), 'completed', ['completed_at' => '2026-11-12 16:00:00+00']);
        $other->estimates()->create(['amount' => '9000.00', 'description' => 'Other account', 'status' => 'approved']);
        $active = $this->repair($profile, 'in_repair', ['created_at' => '2026-11-10 00:00:00+00']);
        $part = InventoryPart::factory()->create(['technician_id' => $profile->id, 'unit_cost' => '0.10', 'quantity' => 10]);
        app(InventoryService::class)->move($profile->user, $part->id, $this->movement('usage', 3, ['repair_request_id' => $active->id]));
        $url = '/api/v1/technician/business-reports';
        $this->actingAs($profile->user)->getJson($url.'?from=2026-11-13&through=2026-11-13')
            ->assertOk()->assertJsonPath('data.requests_received', 1)->assertJsonPath('data.completed_repairs', 1)
            ->assertJsonPath('data.completed_approved_estimates', '2100.50')->assertJsonPath('data.parts_cost', '0.30')->assertJsonPath('data.parts_used', 3);
        $csv = $this->get($url.'/export?format=csv&from=2026-11-13&through=2026-11-13')->assertOk()->assertDownload();
        $this->assertStringContainsString("'=HYPERLINK", $csv->streamedContent());
        $this->assertStringNotContainsString($other->reference, $csv->streamedContent());
        $pdf = $this->get($url.'/export?format=pdf&from=2026-11-13&through=2026-11-13')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('PHP 2100.50', $pdf->getContent());
        $this->getJson($url.'?from=2026-11-14&through=2026-11-13')->assertUnprocessable();
        $this->getJson($url.'?from=2020-01-01&through=2026-11-13')->assertUnprocessable();
    }

    public function test_large_valid_stock_movements_keep_report_totals_exact(): void
    {
        $profile = TechnicianProfile::factory()->create();
        $this->upgrade($profile);
        $part = InventoryPart::factory()->create(['technician_id' => $profile->id, 'quantity' => 1000000, 'unit_cost' => '9999999999.99']);
        $repair = $this->repair($profile);
        app(InventoryService::class)->move($profile->user, $part->id, $this->movement('usage', 1000000, ['repair_request_id' => $repair->id]));

        $this->actingAs($profile->user)->getJson('/api/v1/technician/business-reports')->assertOk()->assertJsonPath('data.parts_cost', '9999999999990000.00');
    }
}
