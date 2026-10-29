<?php

declare(strict_types=1);

namespace UyuniTour\Domain\Reservations;

enum ReservationStatus: string
{
    case Confirmed = 'confirmada';
    case Cancelled = 'cancelada';
}
