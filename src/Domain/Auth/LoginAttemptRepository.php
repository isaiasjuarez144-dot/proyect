<?php

declare(strict_types=1);

namespace UyuniTour\Domain\Auth;

interface LoginAttemptRepository
{
    public function isLocked(string $identifierHash): bool;

    public function recordFailure(string $identifierHash): void;

    public function clear(string $identifierHash): void;
}
