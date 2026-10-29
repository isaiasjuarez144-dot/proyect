<?php

declare(strict_types=1);

namespace UyuniTour\Infrastructure\Http;

use JsonException;
use UyuniTour\Domain\Shared\ValidationException;

final readonly class Request
{
    public function __construct(
        public string $method,
        public string $path,
        public array $headers,
        public string $body,
        public string $ipAddress,
        public array $query = []
    ) {
    }

    public static function fromGlobals(): self
    {
        $headers = function_exists('getallheaders') ? (getallheaders() ?: []) : [];
        $headers = array_change_key_case($headers, CASE_LOWER);
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] ??= $value;
            }
        }
        $authorization = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? null;
        if (is_string($authorization)) {
            $headers['authorization'] ??= $authorization;
        }

        return new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/',
            $headers,
            file_get_contents('php://input') ?: '',
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            self::queryParameters()
        );
    }

    private static function queryParameters(): array
    {
        $query = [];
        parse_str((string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_QUERY) ?: ''), $query);

        return $query;
    }

    public function json(): array
    {
        if ($this->body === '') {
            throw new ValidationException('El cuerpo JSON es obligatorio.');
        }

        try {
            $decoded = json_decode($this->body, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new ValidationException('El cuerpo JSON no es válido.');
        }
        if (!$decoded instanceof \stdClass) {
            throw new ValidationException('El cuerpo debe ser un objeto JSON.');
        }

        return get_object_vars($decoded);
    }

    public function bearerToken(): ?string
    {
        $header = $this->headers['authorization'] ?? '';
        if (!preg_match('/^Bearer ([A-Za-z0-9._~-]+)$/D', $header, $matches)) {
            return null;
        }

        return $matches[1];
    }

    public function header(string $name): ?string
    {
        $value = $this->headers[strtolower($name)] ?? null;

        return is_string($value) ? trim($value) : null;
    }
}
