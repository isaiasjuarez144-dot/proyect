<?php

declare(strict_types=1);

use UyuniTour\Application\Users\UserManagementService;
use UyuniTour\Domain\Shared\ValidationException;
use UyuniTour\Infrastructure\Bootstrap\ApplicationFactory;
use UyuniTour\Infrastructure\Database\PdoConnectionFactory;

require dirname(__DIR__) . '/vendor/autoload.php';

$connection = PdoConnectionFactory::createFromEnvironment();
$users = ApplicationFactory::users($connection);
if ($users->hasAnyAdministrator()) {
    fwrite(STDOUT, "An administrator already exists; no changes were made.\n");
    exit(0);
}

if (!function_exists('stream_isatty') || !stream_isatty(STDIN)) {
    fwrite(STDERR, "Run this command from an interactive terminal to enter the initial credentials securely.\n");
    exit(1);
}

fwrite(STDOUT, 'Administrator email: ');
$email = trim((string) fgets(STDIN));
fwrite(STDOUT, 'Password: ');
exec('stty -echo', $output, $status);
if ($status !== 0) {
    throw new RuntimeException('Could not disable terminal echo for password input.');
}
try {
    $password = rtrim((string) fgets(STDIN), "\r\n");
    fwrite(STDOUT, PHP_EOL . 'Confirm password: ');
    $confirmation = rtrim((string) fgets(STDIN), "\r\n");
} finally {
    exec('stty echo');
    fwrite(STDOUT, PHP_EOL);
}

if (!hash_equals($password, $confirmation)) {
    fwrite(STDERR, "Passwords do not match.\n");
    exit(1);
}

$service = new UserManagementService($users, ApplicationFactory::passwordHasher());
try {
    $created = $service->initializeFirstAdministrator($email, $password);
} catch (ValidationException $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, $created
    ? "Initial administrator created. Credentials were not displayed or stored in source.\n"
    : "An administrator already exists; no changes were made.\n");
