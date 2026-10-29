<?php

declare(strict_types=1);

namespace UyuniTour\Application\Catalog;

use UyuniTour\Domain\Catalog\Tour;
use UyuniTour\Domain\Catalog\TourRepository;
use UyuniTour\Domain\Identity\Role;
use UyuniTour\Domain\Identity\User;
use UyuniTour\Domain\Shared\AuthorizationException;
use UyuniTour\Domain\Shared\NotFoundException;
use UyuniTour\Domain\Shared\ValidationException;

final class TourCatalogService
{
    public function __construct(private readonly TourRepository $tours)
    {
    }

    public function list(User $actor): array
    {
        self::assertCanManage($actor);

        return $this->tours->list();
    }

    public function create(User $actor, array $input): Tour
    {
        self::assertCanManage($actor);
        self::rejectUnknownFields($input, false);
        $fields = self::validatedFields($input);

        return $this->tours->create($actor->id, ...$fields);
    }

    public function update(User $actor, int $id, array $input): Tour
    {
        self::assertCanManage($actor);
        self::rejectUnknownFields($input);
        $current = $this->tours->findById($id) ?? throw new NotFoundException(
            'Tour no encontrado.'
        );
        if ($input === []) {
            throw new ValidationException('Indica al menos un cambio.');
        }

        $fields = self::validatedFields([
            'name' => array_key_exists('name', $input) ? $input['name'] : $current->name,
            'description' => array_key_exists('description', $input)
                ? $input['description']
                : $current->description,
            'itinerary' => array_key_exists('itinerary', $input)
                ? $input['itinerary']
                : $current->itinerary,
            'duration_days' => array_key_exists('duration_days', $input)
                ? $input['duration_days']
                : $current->durationDays,
            'base_price' => array_key_exists('base_price', $input)
                ? $input['base_price']
                : $current->basePrice,
        ]);
        $isActive = array_key_exists('active', $input) ? $input['active'] : $current->isActive;
        if (!is_bool($isActive)) {
            throw new ValidationException('El estado activo debe ser booleano.');
        }
        self::rejectUnknownFields($input);

        return $this->tours->update(
            $actor->id,
            $id,
            $fields['name'],
            $fields['description'],
            $fields['itinerary'],
            $fields['durationDays'],
            $fields['basePrice'],
            $isActive
        );
    }

    private static function validatedFields(array $input): array
    {
        self::rejectUnknownFields($input);
        $name = self::text($input, 'name', 150, true);
        $description = self::text($input, 'description', 65535);
        $itinerary = self::text($input, 'itinerary', 65535);
        $durationDays = $input['duration_days'] ?? null;
        if (!is_int($durationDays) || $durationDays < 1) {
            throw new ValidationException(
                'La duración debe ser un número entero de días mayor que cero.'
            );
        }

        $basePrice = $input['base_price'] ?? null;
        if (!is_int($basePrice) && !is_float($basePrice) && !is_string($basePrice)) {
            throw new ValidationException(
                'El precio base debe ser un número no negativo con hasta dos decimales.'
            );
        }
        $priceString = (string) $basePrice;
        if (
            !preg_match('/^(0|[1-9][0-9]*)(?:\.([0-9]{1,2}))?$/D', $priceString, $priceParts)
            || strlen($priceParts[1]) > 8
        ) {
            throw new ValidationException(
                'El precio base debe ser un número no negativo con hasta dos decimales.'
            );
        }
        $wholePrice = str_pad($priceParts[1], 8, '0', STR_PAD_LEFT);
        $fractionalPrice = str_pad($priceParts[2] ?? '', 2, '0');
        if ($wholePrice === '99999999' && $fractionalPrice !== '00') {
            throw new ValidationException(
                'El precio base debe ser un número no negativo con hasta dos decimales.'
            );
        }

        return [
            'name' => $name,
            'description' => $description,
            'itinerary' => $itinerary,
            'durationDays' => $durationDays,
            'basePrice' => ((int) $priceParts[1]) . '.' . $fractionalPrice,
        ];
    }

    private static function text(
        array $input,
        string $field,
        int $maxBytes,
        bool $required = false
    ): string {
        $value = $input[$field] ?? null;
        if (!is_string($value)) {
            throw new ValidationException('El campo ' . $field . ' debe ser texto.');
        }
        $value = trim($value);
        $tooLong = $field === 'name'
            ? mb_strlen($value, 'UTF-8') > $maxBytes
            : strlen($value) > $maxBytes;
        if (($required && $value === '') || $tooLong) {
            throw new ValidationException(
                $field === 'name'
                    ? 'El nombre es obligatorio y no puede superar 150 caracteres.'
                    : 'El campo ' . $field . ' supera el tamaño permitido.'
            );
        }

        return $value;
    }

    private static function rejectUnknownFields(array $input, bool $allowActive = true): void
    {
        $allowed = ['name', 'description', 'itinerary', 'duration_days', 'base_price'];
        if ($allowActive) {
            $allowed[] = 'active';
        }
        foreach (array_keys($input) as $field) {
            if (!in_array($field, $allowed, true)) {
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
