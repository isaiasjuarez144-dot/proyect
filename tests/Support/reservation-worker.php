<?php

declare(strict_types=1);

use UyuniTour\Infrastructure\Bootstrap\ApplicationFactory;
use UyuniTour\Infrastructure\Database\PdoConnectionFactory;
use UyuniTour\Infrastructure\Http\Request;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$startAt = (float) getenv('UYUNI_START_MICROTIME');
while (microtime(true) < $startAt) {
    usleep(1000);
}
$input = json_decode((string) file_get_contents('php://stdin'), true, 512, JSON_THROW_ON_ERROR);
$request = new Request(
    'POST',
    '/api/v1/reservations',
    [
        'authorization' => 'Bearer ' . (getenv('UYUNI_TOKEN') ?: ''),
        'idempotency-key' => (string) ($input['key'] ?? ''),
        'content-type' => 'application/json',
    ],
    json_encode($input['body'] ?? [], JSON_THROW_ON_ERROR),
    '127.0.0.1'
);
$response = ApplicationFactory::kernel(
    PdoConnectionFactory::createFromEnvironment(getenv('DB_TEST_DATABASE') ?: null)
)->handle($request);
fwrite(STDOUT, (string) $response->status);
