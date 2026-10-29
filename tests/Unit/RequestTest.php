<?php

declare(strict_types=1);

namespace UyuniTour\Tests\Unit;

use PHPUnit\Framework\TestCase;
use UyuniTour\Infrastructure\Http\Request;

final class RequestTest extends TestCase
{
    public function testItReadsAuthorizationForwardedByApacheRewrite(): void
    {
        $originalServer = $_SERVER;
        $_SERVER = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/api/v1/auth/me',
            'REDIRECT_HTTP_AUTHORIZATION' => 'Bearer forwarded-token',
        ];

        try {
            self::assertSame('forwarded-token', Request::fromGlobals()->bearerToken());
        } finally {
            $_SERVER = $originalServer;
        }
    }
}
