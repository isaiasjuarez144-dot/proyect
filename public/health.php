<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

try {
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        getenv('DB_HOST'),
        getenv('DB_PORT') ?: '3306',
        getenv('DB_DATABASE')
    );
    $connection = new PDO(
        $dsn,
        getenv('DB_USERNAME'),
        getenv('DB_PASSWORD'),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $connection->query('SELECT 1');
} catch (PDOException $exception) {
    error_log('Database health check failed: ' . $exception->getMessage());
    http_response_code(503);
    echo json_encode(['status' => 'unavailable', 'database' => 'unavailable']);
    exit;
}

echo json_encode(['status' => 'ok', 'database' => 'ok']);
