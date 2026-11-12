<?php

namespace Tests\Feature;

use App\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicSubscriptionPlansTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertDisposablePostgresDatabase();
    }

    protected function setUp(): void
    {
        if (getenv('RUN_POSTGRES_TESTS') !== '1') {
            $this->markTestSkipped('Requires dedicated PostgreSQL integration database.');
        }
        parent::setUp();
    }

    public function test_guests_can_compare_three_plans_without_receiving_private_subscription_data(): void
    {
        $response = $this->getJson('/api/v1/subscription-plans')->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.slug', 'free')->assertJsonPath('data.0.price', '0.00')
            ->assertJsonPath('data.1.slug', 'pro')->assertJsonPath('data.1.price', '199.00')
            ->assertJsonPath('data.2.slug', 'premium')->assertJsonPath('data.2.price', '399.00')
            ->assertJsonPath('data.2.duration_days', 30);

        foreach ($response->json('data') as $plan) {
            $this->assertSame(['id', 'slug', 'name', 'description', 'price', 'duration_days', 'features', 'is_active'], array_keys($plan));
        }
        $this->assertDatabaseCount('technician_subscriptions', 0);
        $this->assertDatabaseCount('subscription_demo_payments', 0);
    }

    public function test_public_pricing_uses_current_database_prices_duration_and_benefits(): void
    {
        SubscriptionPlan::where('slug', 'premium')->firstOrFail()->update([
            'price' => '149.00', 'duration_days' => 45,
            'description' => 'Updated public plan description.',
            'features' => ['Current public plan benefit'],
        ]);

        $this->getJson('/api/v1/subscription-plans')->assertOk()
            ->assertJsonPath('data.1.slug', 'premium')
            ->assertJsonPath('data.1.price', '149.00')
            ->assertJsonPath('data.1.duration_days', 45)
            ->assertJsonPath('data.1.description', 'Updated public plan description.')
            ->assertJsonPath('data.1.features', ['Current public plan benefit']);
    }

    public function test_inactive_plans_are_hidden_without_deleting_their_records(): void
    {
        SubscriptionPlan::whereIn('slug', ['pro', 'premium'])->update(['is_active' => false]);

        $this->getJson('/api/v1/subscription-plans')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.slug', 'free');

        $this->assertDatabaseCount('subscription_plans', 3);
        $this->assertDatabaseHas('subscription_plans', ['slug' => 'premium', 'is_active' => false]);
    }

    public function test_no_active_plans_returns_an_empty_public_catalog(): void
    {
        SubscriptionPlan::query()->update(['is_active' => false]);

        $this->getJson('/api/v1/subscription-plans')->assertOk()->assertExactJson(['data' => []]);
    }
}
