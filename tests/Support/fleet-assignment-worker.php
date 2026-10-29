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
$operation = $input['operation'] ?? 'assignment';
if ($operation === 'maintenance') {
    $method = 'POST';
    $path = '/api/v1/vehicles/' . (int) $input['vehicle_id'] . '/maintenance';
    $body = $input['body'];
} else {
    $method = 'PUT';
    $path = '/api/v1/departures/' . (int) $input['departure_id'] . '/assignment';
    $body = [
        'vehicle_id' => (int) $input['vehicle_id'],
        'driver_user_id' => (int) $input['driver_user_id'],
    ];
}
$request = new Request(
    $method,
    $path,
    [
        'authorization' => 'Bearer ' . (getenv('UYUNI_TOKEN') ?: ''),
        'content-type' => 'application/json',
    ],
    json_encode($body, JSON_THROW_ON_ERROR),
    '127.0.0.1'
);
$response = ApplicationFactory::kernel(
    PdoConnectionFactory::createFromEnvironment(getenv('DB_TEST_DATABASE') ?: null)
)->handle($request);
$errorCode = $response->body['error']['code'] ?? '';
$errorMessage = $response->body['error']['message'] ?? '';
fwrite(STDOUT, $response->status . ':' . $errorCode . ':' . $errorMessage);
