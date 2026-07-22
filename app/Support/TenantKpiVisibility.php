<?php

namespace App\Support;

use App\Models\Tenant;

class TenantKpiVisibility
{
    public const DASHBOARD = 'dashboard';
    public const CUSTOMERS_INDEX = 'customers_index';
    public const CUSTOMER_SHOW = 'customer_show';
    public const ANIMALS_INDEX = 'animals_index';
    public const VENTAS_INDEX = 'ventas_index';
    public const SERVICIOS_INDEX = 'servicios_index';
    public const CLUBES_INDEX = 'clubes_index';

    public static function all(): array
    {
        return [
            self::DASHBOARD => [
                'label' => 'Dashboard',
                'description' => 'Resumen general de metricas y actividad.',
                'route' => 'client.dashboard',
            ],
            self::CUSTOMERS_INDEX => [
                'label' => 'Clientes',
                'description' => 'KPIs del listado de clientes.',
                'route' => 'client.customers.index',
            ],
            self::CUSTOMER_SHOW => [
                'label' => 'Detalle de cliente',
                'description' => 'KPIs dinamicos por pestana dentro del cliente.',
                'route' => 'client.customers.show',
            ],
            self::ANIMALS_INDEX => [
                'label' => 'Caballos / pacientes',
                'description' => 'KPIs del listado de pacientes.',
                'route' => 'client.animals.index',
            ],
            self::VENTAS_INDEX => [
                'label' => 'Ventas',
                'description' => 'KPIs de ventas, notas y pacientes atendidos.',
                'route' => 'client.ventas.index',
            ],
            self::SERVICIOS_INDEX => [
                'label' => 'Servicios',
                'description' => 'KPIs de catalogo, inventario y movimientos.',
                'route' => 'client.servicios.index',
            ],
            self::CLUBES_INDEX => [
                'label' => 'Clubes',
                'description' => 'KPIs de clubes, miembros y pacientes sin club.',
                'route' => 'client.clubes.index',
            ],
        ];
    }

    public static function keys(): array
    {
        return array_keys(self::all());
    }

    public static function defaults(): array
    {
        return array_fill_keys(self::keys(), true);
    }

    public static function normalize(?array $visibility): array
    {
        if ($visibility === null) {
            return self::defaults();
        }

        $normalized = self::defaults();

        foreach (self::keys() as $key) {
            if (! array_key_exists($key, $visibility)) {
                continue;
            }

            $normalized[$key] = self::toBoolean($visibility[$key]);
        }

        return $normalized;
    }

    public static function isVisible(?Tenant $tenant, string $key): bool
    {
        $visibility = self::normalize($tenant?->kpi_visibility);

        return $visibility[$key] ?? true;
    }

    private static function toBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array($value, [1, '1', 'true', 'on', 'yes'], true);
    }
}
