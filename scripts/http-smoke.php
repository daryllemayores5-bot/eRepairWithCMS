<?php

declare(strict_types=1);

// A real HTTP/cookie/CSRF exercise against the local DEMO database only.
// It creates one clearly labeled completed repair, private fixture and review.
$workspace = dirname(__DIR__);
$base = rtrim($argv[1] ?? 'http://127.0.0.1:8000', '/');
if (! in_array(parse_url($base, PHP_URL_HOST), ['127.0.0.1', 'localhost'], true)) {
    fwrite(STDERR, "Only a loopback demonstration server may be tested.\n");
    exit(1);
}
if (! extension_loaded('curl') || ! extension_loaded('gd')) {
    fwrite(STDERR, "PHP curl and GD are required.\n");
    exit(1);
}
$passwordFile = $workspace.'/.local/demo-password';
if (! is_file($passwordFile)) {
    fwrite(STDERR, "Seed the local demonstration database first.\n");
    exit(1);
}
$password = trim(file_get_contents($passwordFile));

require_once __DIR__.'/lib/DemoHttpClient.php';

function check(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
    fwrite(STDOUT, '[PASS] '.$message."\n");
}

try {
    $customer = new DemoHttpClient($base);
    $technician = new DemoHttpClient($base);
    $stranger = new DemoHttpClient($base);
    $health = $customer->request('GET', '/api/v1/health');
    check(($health['data']['database'] ?? '') === 'connected', 'HTTP readiness reaches PostgreSQL');
    $uninitialized = new DemoHttpClient($base);
    $uninitialized->request('POST', '/api/v1/login', ['email' => 'customer1@erepair.test', 'password' => $password], 419);
    check(true, 'CSRF rejects a real HTTP mutation without its token');
    $customer->login('customer1@erepair.test', $password);
    $technician->login('technician1@erepair.test', $password);
    $stranger->login('customer2@erepair.test', $password);
    check(($customer->request('GET', '/api/v1/user')['data']['role'] ?? '') === 'customer', 'Customer cookie authentication');
    check(($technician->request('GET', '/api/v1/user')['data']['role'] ?? '') === 'technician', 'Technician cookie authentication');

    $profile = $technician->request('GET', '/api/v1/technician/profile')['data'];
    $lookups = $customer->request('GET', '/api/v1/lookups')['data'];
    $device = $profile['device_types'][0]['id'];
    $specializations = array_column($profile['specializations'], 'id');
    $categories = array_values(array_filter($lookups['repair_categories'], fn ($category) => in_array($category['specialization_id'], $specializations, true)));
    $category = $categories[0];
    $results = $customer->request('GET', '/api/v1/technicians?'.http_build_query([
        'city' => $profile['city'], 'device_type' => $device, 'specialization' => $category['specialization_id'], 'availability' => 'available',
    ]));
    check(in_array($profile['id'], array_column($results['data'], 'id'), true), 'Finder returns the compatible approved technician');

    $fixture = $workspace.'/.local/http-smoke-device.png';
    $image = imagecreatetruecolor(1536, 800);
    $background = imagecolorallocate($image, 226, 232, 240);
    imagefill($image, 0, 0, $background);
    imagestring($image, 5, 25, 70, 'DEMO DEVICE PHOTO', imagecolorallocate($image, 15, 23, 42));
    imagepng($image, $fixture, 0);
    imagedestroy($image);
    check(filesize($fixture) > 2 * 1024 * 1024 && filesize($fixture) < 5 * 1024 * 1024, 'The upload fixture exercises the documented 5 MB limit above PHP default limits');
    $start = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify('+45 days')->setTime(10, 0);
    $appointment = [
        'starts_at' => $start->format(DATE_ATOM), 'ends_at' => $start->modify('+1 hour')->format(DATE_ATOM),
        'service_location' => 'DEMO HTTP test meeting point, Santa Cruz town center',
    ];
    $created = $customer->request('POST', '/api/v1/repair-requests', [
        'technician_profile_id' => $profile['id'], 'device_type_id' => $device,
        'device_brand_id' => $lookups['device_brands'][0]['id'], 'repair_category_id' => $category['id'],
        'model' => 'DEMO HTTP smoke device', 'problem_description' => 'DEMO HTTP SMOKE TEST. Please diagnose the battery and provide an estimate before repair.',
        ...$appointment, 'photos[0]' => new CURLFile($fixture, 'image/png', 'demo-device.png'),
    ], 201, true)['data'];
    $id = $created['id'];
    $path = '/api/v1/repair-requests/'.$id;
    $detail = $customer->request('GET', $path)['data'];
    check(count($detail['attachments']) === 1, 'Multipart photo is attached to the persisted repair');
    $attachment = $detail['attachments'][0]['id'];
    $customer->request('GET', '/api/v1/attachments/'.$attachment);
    $stranger->request('GET', $path, expected: 403);
    $stranger->request('GET', '/api/v1/attachments/'.$attachment, expected: 403);
    check(true, 'Unrelated customer cannot read the repair or private photo');

    $technician->request('POST', '/api/v1/technician/repair-requests/'.$id.'/accept');
    $technician->request('PATCH', '/api/v1/appointments/'.$detail['appointments'][0]['id'], $appointment);
    foreach (['scheduled', 'diagnosing'] as $status) {
        $technician->request('PATCH', $path.'/status', ['status' => $status]);
    }
    $technician->request('POST', $path.'/estimates', ['amount' => '1500.50', 'description' => 'DEMO estimate: battery replacement and labor, subject to customer approval.']);
    $technician->request('PATCH', $path.'/status', ['status' => 'in_repair'], 422);
    $customer->request('POST', $path.'/approve-estimate');
    $technician->request('PATCH', $path.'/status', ['status' => 'ready_for_pickup']);
    $completed = $customer->request('PATCH', $path.'/status', ['status' => 'completed'])['data'];
    check($completed['status'] === 'completed' && count($completed['history']) >= 8, 'Acceptance, appointment, estimate approval and completion persist with history');
    check($completed['appointments'][0]['status'] === 'completed', 'Completion releases the active appointment');
    $review = ['repair_request_id' => $id, 'rating' => 5, 'body' => 'DEMO HTTP smoke review: clear estimate and helpful updates.'];
    $customer->request('POST', '/api/v1/reviews', $review, 201);
    $customer->request('POST', '/api/v1/reviews', $review, 422);
    check(true, 'Completed-repair review is accepted once');
    $notifications = $customer->request('GET', '/api/v1/notifications');
    check(count($notifications['data']) > 0, 'Repair database notifications are delivered');
    $customer->request('POST', '/api/v1/logout', expected: 204);
    $customer->request('GET', '/api/v1/user', expected: 401);
    check(true, 'Logout invalidates the real HTTP session');
    fwrite(STDOUT, "HTTP demo workflow passed. This does not verify frontend rendering, Statamic CP or mobile browser behavior.\n");
} catch (Throwable $exception) {
    fwrite(STDERR, '[FAIL] '.$exception->getMessage()."\n");
    exit(1);
}
