<?php

declare(strict_types=1);

namespace UyuniTour\Domain\Identity;

enum Role: string
{
    case Administrator = 'administrator';
    case Operator = 'operator';
    case DriverGuide = 'driver_guide';
    case Auditor = 'auditor';
}
