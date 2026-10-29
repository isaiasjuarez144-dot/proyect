<?php

declare(strict_types=1);

namespace UyuniTour\Infrastructure\Http;

final readonly class Response
{
    public function __construct(
        public int $status,
        public array $body = [],
        public array $headers = []
    ) {
    }

    public function send(): never
    {
        http_response_code($this->status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }

        if ($this->status !== 204) {
            echo json_encode($this->body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        exit;
    }
}
