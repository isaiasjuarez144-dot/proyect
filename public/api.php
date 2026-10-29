<?php

declare(strict_types=1);

use UyuniTour\Infrastructure\Bootstrap\ApplicationFactory;
use UyuniTour\Infrastructure\Database\PdoConnectionFactory;
use UyuniTour\Infrastructure\Http\Request;
use UyuniTour\Infrastructure\Http\Response;

require dirname(__DIR__) . '/vendor/autoload.php';

$request = Request::fromGlobals();
try {
    $response = ApplicationFactory::kernel(PdoConnectionFactory::createFromEnvironment())
        ->handle($request);
} catch (Throwable $exception) {
    error_log('API bootstrap failed: ' . $exception::class);
    $response = new Response(503, [
        'error' => [
            'code' => 'service_unavailable',
            'message' => 'El servicio no está disponible temporalmente.',
        ],
    ]);
}
$response->send();
