<?php

declare(strict_types=1);

use UyuniTour\Infrastructure\Database\MigrationRunner;
use UyuniTour\Infrastructure\Database\PdoConnectionFactory;

require dirname(__DIR__) . '/vendor/autoload.php';

$testDatabase = getenv('DB_TEST_DATABASE') ?: 'uyuni_tour_test';
$applicationDatabase = getenv('DB_DATABASE') ?: getenv('MYSQL_DATABASE') ?: '';
if ($applicationDatabase !== '' && $testDatabase === $applicationDatabase) {
    throw new RuntimeException('DB_TEST_DATABASE must differ from the application database.');
}

$connection = PdoConnectionFactory::createFromEnvironment($testDatabase);
(new MigrationRunner($connection, dirname(__DIR__) . '/database/migrations'))->run();
