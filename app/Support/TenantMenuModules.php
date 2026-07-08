<?php

namespace App\Support;

class TenantMenuModules
{
    public const DASHBOARD = 'dashboard';
    public const CUSTOMERS = 'customers';
    public const ANIMALS = 'animals';
    public const CLUBS = 'clubs';
    public const SALES = 'sales';
    public const SERVICES = 'services';
    public const AGENDA = 'agenda';

    public static function all(): array
    {
        return [
            self::CUSTOMERS => [
                'label' => 'Clientes',
                'description' => 'Directorio de clientes y accesos a portal/app.',
                'route' => 'client.customers.index',
            ],
            self::ANIMALS => [
                'label' => 'Pacientes',
                'description' => 'Expedientes clinicos y datos de pacientes.',
                'route' => 'client.animals.index',
            ],
            self::SALES => [
                'label' => 'Ventas',
                'description' => 'Notas, tickets, cobros y saldos.',
                'route' => 'client.ventas.index',
            ],
            self::AGENDA => [
                'label' => 'Agenda',
                'description' => 'Calendario, citas y disponibilidad.',
                'route' => 'client.agenda.index',
            ],
            self::CLUBS => [
                'label' => 'Clubes',
                'description' => 'Gestion de clubes y grupos de pacientes.',
                'route' => 'client.clubes.index',
            ],
            self::SERVICES => [
                'label' => 'Servicios',
                'description' => 'Catalogo de productos, servicios e inventario.',
                'route' => 'client.servicios.index',
            ],
            self::DASHBOARD => [
                'label' => 'Dashboard',
                'description' => 'Resumen general de metricas y actividad.',
                'route' => 'client.dashboard',
            ],
        ];
    }

    public static function keys(): array
    {
        return array_keys(self::all());
    }

    public static function normalize(?array $modules): array
    {
        if ($modules === null) {
            return self::keys();
        }

        $valid = array_values(array_intersect(self::keys(), $modules));

        return $valid ?: self::keys();
    }

    public static function isVisible(?array $modules, string $module): bool
    {
        return in_array($module, self::normalize($modules), true);
    }

    public static function moduleForRoute(string $routeName): ?string
    {
        foreach (self::all() as $module => $config) {
            if (($config['route'] ?? null) === $routeName) {
                return $module;
            }
        }

        return null;
    }
}
