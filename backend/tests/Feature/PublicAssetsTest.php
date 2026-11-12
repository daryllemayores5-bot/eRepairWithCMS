<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicAssetsTest extends TestCase
{
    public function test_editorial_assets_are_served_without_a_filesystem_link(): void
    {
        Storage::fake('public');
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jG9cAAAAASUVORK5CYII=');
        Storage::disk('public')->put('website/demo-image.png', $png);

        $response = $this->get('/storage/website/demo-image.png')->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertSame($png, $response->streamedContent());
    }

    public function test_missing_public_assets_return_not_found(): void
    {
        Storage::fake('public');
        $this->get('/storage/website/missing.png')->assertNotFound();
    }

    public function test_public_asset_route_never_serves_private_repair_files_or_parent_paths(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        Storage::disk('local')->put('repairs/private-device.txt', 'Private repair documentation');

        $this->assertFalse(config('filesystems.disks.local.serve'));
        $this->get('/storage/repairs/private-device.txt')->assertNotFound()->assertDontSee('Private repair documentation');
        $this->get('/storage/%2e%2e/private/repairs/private-device.txt')->assertNotFound()->assertDontSee('Private repair documentation');
    }

    public function test_public_asset_serving_does_not_allow_unsigned_uploads(): void
    {
        Storage::fake('public');
        $this->put('/storage/website/unauthorized.png', ['file' => 'untrusted'])->assertForbidden();
        Storage::disk('public')->assertMissing('website/unauthorized.png');
    }
}
