<?php

declare(strict_types=1);

use UyuniTour\Infrastructure\Bootstrap\ApplicationFactory;
use UyuniTour\Infrastructure\Database\PdoConnectionFactory;

require dirname(__DIR__) . '/vendor/autoload.php';

if (!function_exists('stream_isatty') || !stream_isatty(STDIN)) {
    fwrite(STDERR, "Run this command from an interactive terminal.\n");
    exit(1);
}

$connection = PdoConnectionFactory::createFromEnvironment();
$administrators = $connection->query(
    "SELECT id, email, is_active FROM users WHERE role = 'administrator' ORDER BY id"
)->fetchAll();

if ($administrators === []) {
    fwrite(STDERR, "No administrator account exists. Use bin/create-admin.php first.\n");
    exit(1);
}

fwrite(STDOUT, "Administrator accounts (inactive accounts cannot log in):\n");
foreach ($administrators as $index => $administrator) {
    $state = (bool) $administrator['is_active'] ? 'active' : 'inactive';
    fwrite(STDOUT, sprintf("%d. %s (%s)\n", $index + 1, $administrator['email'], $state));
}
fwrite(STDOUT, 'Select account number: ');
$selection = trim((string) fgets(STDIN));
if (!ctype_digit($selection) || (int) $selection < 1 || (int) $selection > count($administrators)) {
    fwrite(STDERR, "Invalid administrator selection.\n");
    exit(1);
}

$administrator = $administrators[(int) $selection - 1];
fwrite(STDOUT, 'New password: ');
exec('stty -echo', $output, $status);
if ($status !== 0) {
    throw new RuntimeException('Could not disable terminal echo for password input.');
}

try {
    $password = rtrim((string) fgets(STDIN), "\r\n");
    fwrite(STDOUT, PHP_EOL . 'Confirm new password: ');
    $confirmation = rtrim((string) fgets(STDIN), "\r\n");
} finally {
    exec('stty echo');
    fwrite(STDOUT, PHP_EOL);
}

if (!hash_equals($password, $confirmation)) {
    fwrite(STDERR, "Passwords do not match.\n");
    exit(1);
}

$hash = ApplicationFactory::passwordHasher()->hash($password);
$users = ApplicationFactory::users($connection);
$users->updatePassword((int) $administrator['id'], $hash);
fwrite(STDOUT, "Password updated for {$administrator['email']}. The password was not displayed or stored in source.\n");
