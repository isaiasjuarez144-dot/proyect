<?php

declare(strict_types=1);

namespace UyuniTour\Application\Reservations;

use UyuniTour\Domain\Identity\Role;
use UyuniTour\Domain\Identity\User;
use UyuniTour\Domain\Reservations\Tourist;
use UyuniTour\Domain\Reservations\TouristRepository;
use UyuniTour\Domain\Shared\AuthorizationException;
use UyuniTour\Domain\Shared\NotFoundException;
use UyuniTour\Domain\Shared\ValidationException;

final class TouristService
{
    public function __construct(private readonly TouristRepository $tourists)
    {
    }

    public function list(User $actor): array
    {
        self::assertCanManage($actor);

        return $this->tourists->list();
    }

    public function create(User $actor, array $input): Tourist
    {
        self::assertCanManage($actor);
        self::rejectUnknown($input);
        [$name, $email, $phone] = self::validate($input);

        return $this->tourists->create($name, $email, $phone);
    }

    public function update(User $actor, int $id, array $input): Tourist
    {
        self::assertCanManage($actor);
        self::rejectUnknown($input);
        [$name, $email, $phone] = self::validate($input);

        return $this->tourists->update($id, $name, $email, $phone);
    }

    private static function validate(array $input): array
    {
        $name = $input['full_name'] ?? null;
        if (!is_string($name) || trim($name) === '' || mb_strlen(trim($name)) > 150) {
            throw new ValidationException('Ingresa el nombre completo (máximo 150 caracteres).', 'full_name');
        }
        $email = $input['email'] ?? null;
        if ($email !== null && $email !== '' && (!is_string($email) || !filter_var($email, FILTER_VALIDATE_EMAIL))) {
            throw new ValidationException('Ingresa un correo válido.', 'email');
        }
        $phone = $input['phone'] ?? null;
        if ($phone !== null && $phone !== '' && (!is_string($phone) || mb_strlen($phone) > 40)) {
            throw new ValidationException('El teléfono no debe superar 40 caracteres.', 'phone');
        }

        return [trim($name), $email === '' ? null : $email, $phone === '' ? null : $phone];
    }

    private static function rejectUnknown(array $input): void
    {
        foreach (array_keys($input) as $field) {
            if (!in_array($field, ['full_name', 'email', 'phone'], true)) {
                throw new ValidationException('El campo ' . $field . ' no está permitido.');
            }
        }
    }

    private static function assertCanManage(User $actor): void
    {
        if (!in_array($actor->role, [Role::Administrator, Role::Operator], true)) {
            throw new AuthorizationException('Permiso insuficiente.');
        }
    }
}
