<?php

namespace App\Support;

class TenantHomeRoutes
{
    public const DEFAULT = 'client.dashboard';

    public static function all(): array
    {
        return [
            'client.dashboard' => [
                'label' => 'Dashboard',
                'description' => 'Resumen general del negocio.',
            ],
            'client.customers.index' => [
                'label' => 'Clientes',
                'description' => 'Listado de clientes y pacientes.',
            ],
            'client.ventas.index' => [
                'label' => 'Ventas',
                'description' => 'Notas, tickets y cobros.',
            ],
            'client.agenda.index' => [
                'label' => 'Agenda',
                'description' => 'Calendario y gestion de citas.',
            ],
            'client.mi-configuracion.index' => [
                'label' => 'Mi configuracion',
                'description' => 'Ajustes del tenant y del equipo.',
            ],
        ];
    }

    public static function keys(): array
    {
        return array_keys(self::all());
    }

    public static function normalize(?string $routeName): string
    {
        return in_array($routeName, self::keys(), true) ? $routeName : self::DEFAULT;
    }
}
