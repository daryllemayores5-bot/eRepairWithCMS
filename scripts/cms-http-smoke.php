<?php

declare(strict_types=1);

// Native CMS HTTP/session checks against the seeded loopback DEMO server only.
// This does not execute browser JavaScript or change operational records.
require_once __DIR__.'/lib/DemoHttpClient.php';

$base = rtrim($argv[1] ?? 'http://127.0.0.1:8000', '/');
if (! in_array(parse_url($base, PHP_URL_HOST), ['127.0.0.1', 'localhost'], true)) {
    fwrite(STDERR, "Only a loopback demonstration server may be tested.\n");
    exit(1);
}
$passwordFile = dirname(__DIR__).'/.local/demo-password';
if (! extension_loaded('curl') || ! is_file($passwordFile)) {
    fwrite(STDERR, "PHP curl and the seeded local demonstration credentials are required.\n");
    exit(1);
}

function verifyCms(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
    fwrite(STDOUT, '[PASS] '.$message."\n");
}

try {
    $password = trim(file_get_contents($passwordFile));
    $admin = new DemoHttpClient($base);
    $admin->request('GET', '/cp/auth/login');
    verifyCms(str_contains($admin->body(), 'data-page='), 'Native Statamic login HTML is delivered');
    $loginMarkup = $admin->body();
    foreach (['css' => 'text/css', 'js' => 'javascript'] as $extension => $mime) {
        if (! preg_match('~(?:src|href)="([^\"]*/vendor/statamic/cp/[^\"]+\.'.$extension.'(?:\?[^\"]*)?)"~', $loginMarkup, $asset)) {
            throw new RuntimeException('Native Control Panel '.$extension.' reference was not found.');
        }
        $assetPath = parse_url(html_entity_decode($asset[1]), PHP_URL_PATH);
        $admin->request('GET', $assetPath);
        verifyCms($admin->body() !== '' && str_contains($admin->contentType(), $mime), 'Native Control Panel '.$extension.' is served with the correct MIME type');
    }
    $admin->request('GET', '/sanctum/csrf-cookie', expected: 204);
    foreach (['/cp/erepair/subscriptions', '/cp/erepair/revenue'] as $path) {
        $admin->request('GET', $path, expected: 401);
    }
    verifyCms(true, 'Subscription and revenue administration reject anonymous access');
    $admin->request('POST', '/cp/auth/login', ['email' => 'admin@erepair.test', 'password' => $password]);
    $admin->request('GET', '/api/v1/user', expected: 401);
    verifyCms(true, 'Native Core login is isolated from marketplace API authentication');
    $admin->request('GET', '/cp/erepair');
    verifyCms(str_contains($admin->body(), 'eRepair Operations') && str_contains($admin->body(), 'Repair requests'), 'Native operations layout renders persisted repair statistics');
    $document = new DOMDocument;
    @$document->loadHTML($admin->body());
    $xpath = new DOMXPath($document);
    $styles = $xpath->query('//head/link[@rel="stylesheet" and starts-with(@href, "/vendor/erepair/css/operations.css?v=")]');
    verifyCms($styles->length === 1 && $xpath->query('//*[@id="statamic"]//style')->length === 0, 'eRepair stylesheet is linked outside Vue-mounted content');
    $admin->request('GET', $styles->item(0)->getAttribute('href'));
    verifyCms(str_contains($admin->contentType(), 'text/css') && str_contains($admin->body(), '.erepair-ops .ops-stats') && str_contains($admin->body(), '#0B1F41'), 'Published eRepair CSS is served with a stylesheet MIME type');
    $admin->request('GET', '/cp/erepair/reports.csv');
    verifyCms(str_starts_with($admin->body(), 'Technician,'), 'Authorized operational CSV report downloads');
    foreach (['/cp/erepair/subscriptions' => 'Subscription Management', '/cp/erepair/revenue' => 'Revenue Dashboard'] as $path => $heading) {
        $admin->request('GET', $path);
        verifyCms(str_contains($admin->body(), $heading) && str_contains($admin->body(), 'NO REAL PAYMENTS'), 'Authorized native '.$heading.' renders with the DEMO notice');
    }
    $admin->request('GET', '/vendor/erepair/js/revenue-projection.js');
    verifyCms($admin->body() !== '' && str_contains($admin->contentType(), 'javascript'), 'Revenue projection JavaScript is delivered with the correct MIME type');
    $content = $admin->request('GET', '/api/v1/content');
    verifyCms(($content['data']['hero_title'] ?? '') === 'Find the Right Repair Technician for Your Device' && count($content['data']['faqs'] ?? []) > 0, 'Published native CMS content reaches the public API');
    foreach (['customer1@erepair.test', 'technician1@erepair.test'] as $email) {
        $participant = new DemoHttpClient($base);
        $participant->login($email, $password);
        $participant->request('GET', '/cp/erepair', expected: 401);
        $participant->request('GET', '/cp/erepair/reports.csv', expected: 401);
        $participant->request('GET', '/cp/erepair/subscriptions', expected: 401);
        $participant->request('GET', '/cp/erepair/revenue', expected: 401);
        $participant->request('POST', '/api/v1/logout', expected: 204);
    }
    verifyCms(true, 'Customer and technician sessions cannot access native operations, reports, subscriptions or revenue');
    $admin->request('GET', '/cp/auth/logout', expected: 302);
    $admin->request('GET', '/cp/erepair', expected: 401);
    $admin->request('GET', '/cp/erepair/subscriptions', expected: 401);
    $admin->request('GET', '/cp/erepair/revenue', expected: 401);
    verifyCms(true, 'Native Core logout closes operations access');
    fwrite(STDOUT, "Native CMS HTTP checks passed. Browser rendering and editorial publication still require browser verification.\n");
} catch (Throwable $exception) {
    fwrite(STDERR, '[FAIL] '.$exception->getMessage()."\n");
    exit(1);
}
