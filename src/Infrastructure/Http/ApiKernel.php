<?php

declare(strict_types=1);

namespace UyuniTour\Infrastructure\Http;

use PDOException;
use Throwable;
use UyuniTour\Application\Auth\AuthService;
use UyuniTour\Application\Audit\AuditService;
use UyuniTour\Application\Catalog\TourCatalogService;
use UyuniTour\Application\Operations\DepartureService;
use UyuniTour\Application\Operations\FleetService;
use UyuniTour\Application\Reservations\ReservationService;
use UyuniTour\Application\Payments\PaymentService;
use UyuniTour\Application\Reservations\TouristService;
use UyuniTour\Application\Users\UserManagementService;
use UyuniTour\Domain\Identity\User;
use UyuniTour\Domain\Shared\AuthenticationException;
use UyuniTour\Domain\Shared\AuthorizationException;
use UyuniTour\Domain\Shared\ConflictException;
use UyuniTour\Domain\Shared\NotFoundException;
use UyuniTour\Domain\Shared\ValidationException;

final class ApiKernel
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly UserManagementService $users,
        private readonly TourCatalogService $tours,
        private readonly DepartureService $departures,
        private readonly FleetService $fleet,
        private readonly TouristService $tourists,
        private readonly ReservationService $reservations,
        private readonly PaymentService $payments,
        private readonly AuditService $audit
    ) {
    }

    public function handle(Request $request): Response
    {
        try {
            return $this->dispatch($request);
        } catch (AuthenticationException) {
            return self::error(401, 'unauthenticated', 'No autenticado o credenciales incorrectas.');
        } catch (AuthorizationException) {
            return self::error(403, 'forbidden', 'Permiso insuficiente.');
        } catch (ConflictException $exception) {
            return self::error(409, 'conflict', $exception->getMessage());
        } catch (NotFoundException) {
            return self::error(404, 'not_found', 'Recurso no encontrado.');
        } catch (ValidationException $exception) {
            return self::error(
                422,
                'validation_error',
                $exception->getMessage(),
                $exception->field
            );
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                return self::error(409, 'conflict', 'El cambio entra en conflicto con un registro existente.');
            }

            error_log('Database request failed: ' . $exception->getCode());

            return self::error(500, 'internal_error', 'No se pudo completar la solicitud.');
        } catch (Throwable $exception) {
            error_log('Unhandled API error: ' . $exception::class);

            return self::error(500, 'internal_error', 'No se pudo completar la solicitud.');
        }
    }

    private function dispatch(Request $request): Response
    {
        $path = rtrim($request->path, '/') ?: '/';
        if ($path === '/api/v1/audit' && $request->method === 'GET') {
            return new Response(200, [
                'data' => $this->audit->list($this->authenticate($request), $request->query),
            ]);
        }
        if ($path === '/api/v1/auth/login' && $request->method === 'POST') {
            $body = $request->json();
            $email = self::stringField($body, 'email');
            $password = self::stringField($body, 'password');
            $result = $this->auth->login($email, $password, $request->ipAddress);

            return new Response(200, [
                'data' => [
                    'token' => $result['token'],
                    'user' => $result['user']->toArray(),
                ],
            ]);
        }

        if ($path === '/api/v1/auth/me' && $request->method === 'GET') {
            return new Response(200, ['data' => $this->authenticate($request)->toArray()]);
        }

        if ($path === '/api/v1/auth/logout' && $request->method === 'POST') {
            $this->authenticate($request);

            return new Response(204);
        }

        if ($path === '/api/v1/tours' && $request->method === 'GET') {
            $actor = $this->authenticate($request);

            return new Response(200, [
                'data' => array_map(
                    static fn ($tour): array => $tour->toArray(),
                    $this->tours->list($actor)
                ),
            ]);
        }

        if ($path === '/api/v1/tours' && $request->method === 'POST') {
            $actor = $this->authenticate($request);

            return new Response(201, [
                'data' => $this->tours->create($actor, $request->json())->toArray(),
            ]);
        }

        if (preg_match('#^/api/v1/tours/([1-9][0-9]*)$#D', $path, $matches)) {
            $actor = $this->authenticate($request);
            $tourId = self::positiveIdentifier($matches[1]);
            if ($request->method === 'PATCH') {
                return new Response(200, [
                    'data' => $this->tours->update($actor, $tourId, $request->json())->toArray(),
                ]);
            }
        }

        if (preg_match('#^/api/v1/tours/([1-9][0-9]*)/departures$#D', $path, $matches)) {
            $actor = $this->authenticate($request);
            if ($request->method === 'POST') {
                return new Response(201, [
                    'data' => $this->departures->create(
                        $actor,
                        self::positiveIdentifier($matches[1]),
                        $request->json()
                    )->toArray(),
                ]);
            }
        }

        if ($path === '/api/v1/departures' && $request->method === 'GET') {
            $actor = $this->authenticate($request);

            return new Response(200, [
                'data' => array_map(
                    static fn ($departure): array => $departure->toArray(),
                    $this->departures->list($actor, $request->query)
                ),
            ]);
        }

        if ($path === '/api/v1/vehicles' && $request->method === 'GET') {
            $actor = $this->authenticate($request);

            return new Response(200, ['data' => $this->fleet->listVehicles($actor)]);
        }

        if ($path === '/api/v1/vehicles' && $request->method === 'POST') {
            $actor = $this->authenticate($request);

            return new Response(201, ['data' => $this->fleet->createVehicle($actor, $request->json())]);
        }

        if (preg_match('#^/api/v1/vehicles/([1-9][0-9]*)$#D', $path, $matches)) {
            $actor = $this->authenticate($request);
            if ($request->method === 'PATCH') {
                return new Response(200, [
                    'data' => $this->fleet->updateVehicle(
                        $actor,
                        self::positiveIdentifier($matches[1]),
                        $request->json()
                    ),
                ]);
            }
        }

        if ($path === '/api/v1/drivers' && $request->method === 'GET') {
            $actor = $this->authenticate($request);

            return new Response(200, ['data' => $this->fleet->listDrivers($actor)]);
        }

        if ($path === '/api/v1/assignments' && $request->method === 'GET') {
            $actor = $this->authenticate($request);

            return new Response(200, ['data' => $this->fleet->listAssignments($actor)]);
        }

        if (preg_match('#^/api/v1/departures/([1-9][0-9]*)/assignment$#D', $path, $matches)) {
            $actor = $this->authenticate($request);
            if ($request->method === 'PUT') {
                return new Response(200, [
                    'data' => $this->fleet->assign(
                        $actor,
                        self::positiveIdentifier($matches[1]),
                        $request->json()
                    ),
                ]);
            }
        }

        if ($path === '/api/v1/maintenance' && $request->method === 'GET') {
            $actor = $this->authenticate($request);

            return new Response(200, ['data' => $this->fleet->listMaintenance($actor, $request->query)]);
        }

        if (preg_match('#^/api/v1/vehicles/([1-9][0-9]*)/maintenance$#D', $path, $matches)) {
            $actor = $this->authenticate($request);
            if ($request->method === 'POST') {
                return new Response(201, [
                    'data' => $this->fleet->createMaintenance(
                        $actor,
                        self::positiveIdentifier($matches[1]),
                        $request->json()
                    ),
                ]);
            }
        }

        if (preg_match('#^/api/v1/maintenance/([1-9][0-9]*)$#D', $path, $matches)) {
            $actor = $this->authenticate($request);
            if ($request->method === 'PATCH') {
                return new Response(200, [
                    'data' => $this->fleet->transitionMaintenance(
                        $actor,
                        self::positiveIdentifier($matches[1]),
                        $request->json()
                    ),
                ]);
            }
        }

        if ($path === '/api/v1/incidents' && $request->method === 'GET') {
            $actor = $this->authenticate($request);

            return new Response(200, ['data' => $this->fleet->listIncidents($actor, $request->query)]);
        }

        if (preg_match('#^/api/v1/departures/([1-9][0-9]*)/incidents$#D', $path, $matches)) {
            $actor = $this->authenticate($request);
            if ($request->method === 'POST') {
                return new Response(201, [
                    'data' => $this->fleet->createIncident(
                        $actor,
                        self::positiveIdentifier($matches[1]),
                        $request->json()
                    ),
                ]);
            }
        }

        if ($path === '/api/v1/my/departures' && $request->method === 'GET') {
            $actor = $this->authenticate($request);

            return new Response(200, ['data' => $this->fleet->listDriverDepartures($actor)]);
        }

        if (preg_match('#^/api/v1/departures/([1-9][0-9]*)$#D', $path, $matches)) {
            $actor = $this->authenticate($request);
            if ($request->method === 'PATCH') {
                $body = $request->json();
                if (array_key_exists('start_at', $body) || array_key_exists('end_at', $body)) {
                    if (count($body) !== 2) {
                        throw new ValidationException('Actualiza inicio y fin juntos, por separado del estado.');
                    }

                    return new Response(200, [
                        'data' => $this->departures->updateSchedule(
                            $actor,
                            self::positiveIdentifier($matches[1]),
                            $body
                        )->toArray(),
                    ]);
                }
                if (array_key_exists('capacity', $body)) {
                    if (count($body) !== 1) {
                        throw new ValidationException('Actualiza la capacidad por separado del estado.');
                    }

                    return new Response(200, [
                        'data' => $this->departures->updateCapacity(
                            $actor,
                            self::positiveIdentifier($matches[1]),
                            $body
                        )->toArray(),
                    ]);
                }

                return new Response(200, [
                    'data' => $this->departures->transition(
                        $actor,
                        self::positiveIdentifier($matches[1]),
                        $body
                    )->toArray(),
                ]);
            }
        }

        if ($path === '/api/v1/tourists' && $request->method === 'GET') {
            $actor = $this->authenticate($request);

            return new Response(200, [
                'data' => array_map(
                    static fn ($tourist): array => $tourist->toArray(),
                    $this->tourists->list($actor)
                ),
            ]);
        }

        if ($path === '/api/v1/tourists' && $request->method === 'POST') {
            $actor = $this->authenticate($request);

            return new Response(201, ['data' => $this->tourists->create($actor, $request->json())->toArray()]);
        }

        if (preg_match('#^/api/v1/tourists/([1-9][0-9]*)$#D', $path, $matches)) {
            $actor = $this->authenticate($request);
            if ($request->method === 'PATCH') {
                return new Response(200, [
                    'data' => $this->tourists->update(
                        $actor,
                        self::positiveIdentifier($matches[1]),
                        $request->json()
                    )->toArray(),
                ]);
            }
        }

        if ($path === '/api/v1/reservations' && $request->method === 'GET') {
            $actor = $this->authenticate($request);

            return new Response(200, [
                'data' => array_map(
                    static fn ($reservation): array => $reservation->toArray(),
                    $this->reservations->list($actor)
                ),
            ]);
        }

        if ($path === '/api/v1/reservations' && $request->method === 'POST') {
            $actor = $this->authenticate($request);

            return new Response(201, [
                'data' => $this->reservations->create(
                    $actor,
                    $request->json(),
                    $request->header('idempotency-key')
                )->toArray(),
            ]);
        }

        if (preg_match('#^/api/v1/reservations/([1-9][0-9]*)/payments$#D', $path, $matches)) {
            $actor = $this->authenticate($request);
            $reservationId = self::positiveIdentifier($matches[1]);
            if ($request->method === 'GET') {
                return new Response(200, [
                    'data' => array_map(
                        static fn ($payment): array => $payment->toArray(),
                        $this->payments->list($actor, $reservationId)
                    ),
                ]);
            }
            if ($request->method === 'POST') {
                return new Response(201, [
                    'data' => $this->payments->create(
                        $actor,
                        $reservationId,
                        $request->json(),
                        $request->header('idempotency-key')
                    )->toArray(),
                ]);
            }
        }

        if (
            preg_match('#^/api/v1/payments/([1-9][0-9]*)/void$#D', $path, $matches)
            && $request->method === 'POST'
        ) {
            $actor = $this->authenticate($request);
            return new Response(200, [
                'data' => $this->payments->void(
                    $actor,
                    self::positiveIdentifier($matches[1]),
                    $request->json()
                )->toArray(),
            ]);
        }

        if (preg_match('#^/api/v1/reservations/([1-9][0-9]*)(?:/(cancel))?$#D', $path, $matches)) {
            $actor = $this->authenticate($request);
            $reservationId = self::positiveIdentifier($matches[1]);
            if (($matches[2] ?? '') === 'cancel' && $request->method === 'POST') {
                return new Response(200, ['data' => $this->reservations->cancel($actor, $reservationId)->toArray()]);
            }

            if (!isset($matches[2]) && $request->method === 'PATCH') {
                return new Response(200, [
                    'data' => $this->reservations->update(
                        $actor,
                        $reservationId,
                        $request->json()
                    )->toArray(),
                ]);
            }
        }

        if ($path === '/api/v1/users' && $request->method === 'GET') {
            $actor = $this->authenticate($request);

            return new Response(200, [
                'data' => array_map(
                    static fn (User $user): array => $user->toArray(),
                    $this->users->list($actor)
                ),
            ]);
        }

        if ($path === '/api/v1/users' && $request->method === 'POST') {
            $actor = $this->authenticate($request);
            $body = $request->json();
            $user = $this->users->create(
                $actor,
                self::stringField($body, 'email'),
                self::stringField($body, 'password'),
                self::stringField($body, 'role')
            );

            return new Response(201, ['data' => $user->toArray()]);
        }

        if (preg_match('#^/api/v1/users/([1-9][0-9]*)(?:/(password))?$#D', $path, $matches)) {
            $actor = $this->authenticate($request);
            $userId = (int) $matches[1];
            if (($matches[2] ?? '') === 'password' && $request->method === 'POST') {
                $body = $request->json();
                $this->users->resetPassword(
                    $actor,
                    $userId,
                    self::stringField($body, 'password')
                );

                return new Response(204);
            }

            if (!isset($matches[2]) && $request->method === 'PATCH') {
                $body = $request->json();
                $role = isset($body['role']) ? self::stringField($body, 'role') : null;
                $active = $body['active'] ?? null;
                if ($active !== null && !is_bool($active)) {
                    throw new ValidationException('El estado activo debe ser booleano.');
                }

                return new Response(200, [
                    'data' => $this->users->update($actor, $userId, $role, $active)->toArray(),
                ]);
            }
        }

        throw new NotFoundException('Ruta no encontrada.');
    }

    private function authenticate(Request $request): User
    {
        $token = $request->bearerToken();
        if ($token === null) {
            throw new AuthenticationException('No autenticado.');
        }

        return $this->auth->authenticatedUser($token);
    }

    private static function stringField(array $body, string $field): string
    {
        if (!isset($body[$field]) || !is_string($body[$field])) {
            throw new ValidationException('El campo ' . $field . ' es obligatorio.');
        }

        return $body[$field];
    }

    private static function positiveIdentifier(string $value): int
    {
        $identifier = filter_var($value, FILTER_VALIDATE_INT);
        if ($identifier === false || $identifier < 1) {
            throw new ValidationException('El identificador no es válido.');
        }

        return $identifier;
    }

    private static function error(
        int $status,
        string $code,
        string $message,
        ?string $field = null
    ): Response {
        $error = ['code' => $code, 'message' => $message];
        if ($field !== null) {
            $error['field'] = $field;
        }

        return new Response($status, ['error' => $error]);
    }
}
