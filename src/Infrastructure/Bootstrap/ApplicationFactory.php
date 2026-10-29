<?php

declare(strict_types=1);

namespace UyuniTour\Infrastructure\Bootstrap;

use PDO;
use RuntimeException;
use UyuniTour\Application\Auth\AuthService;
use UyuniTour\Application\Audit\AuditService;
use UyuniTour\Application\Catalog\TourCatalogService;
use UyuniTour\Application\Operations\DepartureService;
use UyuniTour\Application\Operations\FleetService;
use UyuniTour\Application\Reservations\ReservationService;
use UyuniTour\Application\Payments\PaymentService;
use UyuniTour\Application\Reservations\TouristService;
use UyuniTour\Application\Users\UserManagementService;
use UyuniTour\Domain\Auth\TokenIssuer;
use UyuniTour\Domain\Identity\UserRepository;
use UyuniTour\Infrastructure\Database\PdoDepartureRepository;
use UyuniTour\Infrastructure\Database\PdoFleetRepository;
use UyuniTour\Infrastructure\Database\PdoReservationRepository;
use UyuniTour\Infrastructure\Database\PdoPaymentRepository;
use UyuniTour\Infrastructure\Database\PdoAuditRepository;
use UyuniTour\Infrastructure\Database\PdoTouristRepository;
use UyuniTour\Infrastructure\Database\PdoLoginAttemptRepository;
use UyuniTour\Infrastructure\Database\PdoTourRepository;
use UyuniTour\Infrastructure\Database\PdoUserRepository;
use UyuniTour\Infrastructure\Http\ApiKernel;
use UyuniTour\Infrastructure\Security\JwtTokenService;
use UyuniTour\Infrastructure\Security\PasswordHasher;

final class ApplicationFactory
{
    public static function kernel(PDO $connection): ApiKernel
    {
        $audit = new PdoAuditRepository($connection);
        $users = new PdoUserRepository($connection);
        $tours = new PdoTourRepository($connection, $audit);
        $departures = new PdoDepartureRepository($connection);
        $fleet = new PdoFleetRepository($connection, $audit);
        $tourists = new PdoTouristRepository($connection);
        $reservations = new PdoReservationRepository($connection, $audit);
        $payments = new PdoPaymentRepository($connection, $audit);
        $passwords = new PasswordHasher();

        return new ApiKernel(
            new AuthService(
                $users,
                new PdoLoginAttemptRepository($connection),
                $passwords,
                self::tokenIssuer()
            ),
            new UserManagementService($users, $passwords),
            new TourCatalogService($tours),
            new DepartureService($departures, $tours),
            new FleetService($fleet),
            new TouristService($tourists),
            new ReservationService($reservations),
            new PaymentService($payments),
            new AuditService($audit)
        );
    }

    public static function users(PDO $connection): UserRepository
    {
        return new PdoUserRepository($connection);
    }

    public static function passwordHasher(): PasswordHasher
    {
        return new PasswordHasher();
    }

    public static function tokenIssuer(): TokenIssuer
    {
        $secret = getenv('JWT_SECRET');
        if ($secret === false || $secret === '') {
            throw new RuntimeException('JWT_SECRET is not configured.');
        }
        $ttl = filter_var(getenv('JWT_TTL_SECONDS') ?: '900', FILTER_VALIDATE_INT);
        if ($ttl === false) {
            throw new RuntimeException('JWT_TTL_SECONDS must be an integer.');
        }

        return new JwtTokenService($secret, $ttl);
    }
}
