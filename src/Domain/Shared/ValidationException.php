<?php

declare(strict_types=1);

namespace UyuniTour\Domain\Shared;

use RuntimeException;

final class ValidationException extends RuntimeException
{
    public function __construct(string $message, public readonly ?string $field = null)
    {
        parent::__construct($message);
    }
}
