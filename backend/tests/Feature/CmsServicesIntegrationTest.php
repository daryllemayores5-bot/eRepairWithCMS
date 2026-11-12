<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CmsAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Statamic\Facades\Entry;
use Tests\TestCase;

class CmsServicesIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private array $createdEntries = [];

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
        config(['sanctum.stateful' => ['127.0.0.1:5173']]);
        $this->withHeader('Origin', 'http://127.0.0.1:5173');
    }

    protected function tearDown(): void
    {
        foreach ($this->createdEntries as $id) {
            Entry::find($id)?->delete();
        }
        parent::tearDown();
    }

    private function editor(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'super' => true]);
        $this->actingAs(app(CmsAccount::class)->provision($admin), 'cp');
    }

    private function serviceData(array $overrides = []): array
    {
        return array_replace([
            '_blueprint' => 'service', 'title' => 'CMS service publication check',
            'slug' => 'cms-service-test-'.Str::lower((string) Str::ulid()),
            'summary' => 'A service description managed through native Statamic.',
            'content' => 'Ask the chosen technician to assess the device and provide an estimate.',
            'display_order' => 990, 'published' => false,
        ], $overrides);
    }

    private function createService(array $data): string
    {
        $response = $this->postJson('/cp/collections/services/entries/default', $data)
            ->assertOk()->assertJsonPath('saved', true);
        $id = $response->json('data.id');
        $this->assertIsString($id);
        $this->createdEntries[] = $id;

        return $id;
    }

    public function test_native_service_create_edit_publish_and_unpublish_control_the_public_api(): void
    {
        $this->editor();
        $data = $this->serviceData();
        $id = $this->createService($data);
        $url = '/cp/collections/services/entries/'.$id;
        $this->getJson('/api/v1/content')->assertOk()->assertJsonMissing(['slug' => $data['slug']]);
        $data['summary'] = 'An updated introduction written by the administrator.';
        $this->patchJson($url, $data)->assertOk()->assertJsonPath('saved', true);
        $this->postJson($url.'/publish')->assertOk()->assertJsonPath('saved', true);
        $this->getJson('/api/v1/content')->assertOk()->assertJsonFragment([
            'slug' => $data['slug'], 'title' => $data['title'], 'summary' => $data['summary'], 'content' => $data['content'],
        ])->assertJsonStructure(['data' => ['faqs', 'announcements', 'pages', 'services']]);
        $this->get('/cp/erepair')->assertOk()->assertSee('/cp/collections/services/entries', false)
            ->assertSee('/cp/globals/website', false);
        $this->get('/cp/collections/services/entries')->assertOk();
        $this->postJson($url.'/unpublish')->assertOk()->assertJsonPath('saved', true);
        $this->getJson('/api/v1/content')->assertOk()->assertJsonMissing(['slug' => $data['slug']]);
        $this->assertFalse(config('statamic.editions.pro'));
    }

    public function test_services_follow_the_administrators_display_order(): void
    {
        $this->editor();
        $later = $this->serviceData(['display_order' => 999, 'published' => true]);
        $earlier = $this->serviceData(['display_order' => 1, 'published' => true]);
        $this->createService($later);
        $this->createService($earlier);
        $services = collect($this->getJson('/api/v1/content')->assertOk()->json('data.services'))->pluck('slug')->all();
        $this->assertLessThan(array_search($later['slug'], $services), array_search($earlier['slug'], $services));
    }

    public function test_native_service_validation_rejects_invalid_content_and_marketplace_users_cannot_edit(): void
    {
        $customer = User::factory()->create();
        $this->actingAs($customer, 'web')->get('/cp/collections/services/entries')->assertRedirect(cp_route('login'));
        $this->editor();
        $this->postJson('/cp/collections/services/entries/default', $this->serviceData([
            'summary' => '', 'content' => '', 'display_order' => -1,
        ]))->assertUnprocessable()->assertJsonValidationErrors(['summary', 'content', 'display_order']);
    }
}
