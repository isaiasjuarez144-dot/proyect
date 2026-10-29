<?php

declare(strict_types=1);

use UyuniTour\Infrastructure\Database\MigrationRunner;
use UyuniTour\Infrastructure\Database\PdoConnectionFactory;

require dirname(__DIR__) . '/vendor/autoload.php';

$database = null;
if (($argv[1] ?? '') === '--test') {
    $applicationDatabase = getenv('DB_DATABASE') ?: '';
    $testDatabase = getenv('DB_TEST_DATABASE') ?: '';
    if ($testDatabase === '' || $testDatabase === $applicationDatabase) {
        fwrite(STDERR, "La base de pruebas debe estar definida y separada de la base de desarrollo.\n");
        exit(1);
    }
    $database = $testDatabase;
} elseif (isset($argv[1])) {
    fwrite(STDERR, "Uso: php bin/migrate.php [--test]\n");
    exit(2);
}

$migrations = new MigrationRunner(
    PdoConnectionFactory::createFromEnvironment($database),
    dirname(__DIR__) . '/database/migrations'
);
$completed = $migrations->run();
if ($completed === []) {
    fwrite(STDOUT, "No hay migraciones pendientes.\n");
    exit(0);
}

foreach ($completed as $migration) {
    fwrite(STDOUT, "Aplicada {$migration}\n");
}
