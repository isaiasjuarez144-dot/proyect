<?php

declare(strict_types=1);

namespace UyuniTour\Domain\Operations;

enum DepartureStatus: string
{
    case Scheduled = 'programada';
    case InProgress = 'en_curso';
    case Completed = 'finalizada';
    case Cancelled = 'cancelada';

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Scheduled => in_array($next, [self::InProgress, self::Cancelled], true),
            self::InProgress => $next === self::Completed,
            self::Completed, self::Cancelled => false,
        };
    }
}
