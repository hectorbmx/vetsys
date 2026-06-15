<?php

namespace App\Support;

class TenantThemePalettes
{
    public const DEFAULT = 'ocean';

    public static function all(): array
    {
        return [
            'ocean' => [
                'label' => 'Oceano',
                'description' => 'Turquesa y azul profundo del panel actual.',
                'primary' => '#38b2ac',
                'sidebar' => '#0f172a',
            ],
            'violet' => [
                'label' => 'Violeta',
                'description' => 'Acento violeta con navegacion indigo.',
                'primary' => '#7c3aed',
                'sidebar' => '#1e1b4b',
            ],
            'forest' => [
                'label' => 'Bosque',
                'description' => 'Verde clinico con navegacion sobria.',
                'primary' => '#059669',
                'sidebar' => '#064e3b',
            ],
            'sunset' => [
                'label' => 'Atardecer',
                'description' => 'Naranja calido con navegacion oscura.',
                'primary' => '#ea580c',
                'sidebar' => '#431407',
            ],
        ];
    }

    public static function keys(): array
    {
        return array_keys(self::all());
    }

    public static function normalize(?string $palette): string
    {
        return in_array($palette, self::keys(), true) ? $palette : self::DEFAULT;
    }
}
