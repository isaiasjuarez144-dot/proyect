<?php

declare(strict_types=1);

namespace UyuniTour\Domain\Auth;

use UyuniTour\Domain\Identity\User;

interface TokenIssuer
{
    public function issue(User $user): string;

    public function subject(string $token): int;
}
