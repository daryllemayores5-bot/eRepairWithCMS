<?php

use Symfony\Component\Process\Process;

// Composer runs this after discovery. Do not simulate the CMS when unavailable.
require dirname(__DIR__).'/vendor/autoload.php';

if (! class_exists(Statamic\Statamic::class)) {
    fwrite(STDOUT, "Statamic is not installed. See docs/statamic-integration.md.\n");
    exit(0);
}

$process = new Process(
    [PHP_BINARY, 'artisan', 'statamic:install', '--ansi'],
    dirname(__DIR__),
);
$process->setTimeout(300);
$status = $process->run(fn ($type, $output) => fwrite(STDOUT, $output));
if ($status !== 0) {
    exit($status);
}
$assets = new Process([PHP_BINARY, 'artisan', 'vendor:publish', '--tag=erepair-cp-assets', '--force', '--ansi'], dirname(__DIR__));
$assets->setTimeout(60);
exit($assets->run(fn ($type, $output) => fwrite(STDOUT, $output)));
