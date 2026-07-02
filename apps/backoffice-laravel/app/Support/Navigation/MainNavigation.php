<?php

namespace App\Support\Navigation;

use App\Support\Navigation\Groups\CatalogsNavigation;
use App\Support\Navigation\Groups\ClientsNavigation;
use App\Support\Navigation\Groups\FinanzasNavigation;
use App\Support\Navigation\Groups\OperationsNavigation;
use App\Support\Navigation\Groups\WhatsAppNavigation;

class MainNavigation
{
    /**
     * Estructura única de navegación para ambos menús
     */
    public static function structure(): array
    {
        return [
            ClientsNavigation::group(),
            OperationsNavigation::group(),
            CatalogsNavigation::group(),
            WhatsAppNavigation::group(),
            FinanzasNavigation::group(),
        ];
    }

    /**
     * Menú de escritorio: grupos con items y descripciones
     */
    public static function groups(): array
    {
        $structure = static::structure();
        // Filtra items nulos (usuarios si no es super admin)
        foreach ($structure as &$group) {
            $group['items'] = array_values(array_filter($group['items']));
            $group['active'] = collect($group['items'])->contains(fn ($item) => $item['active']);
        }

        return $structure;
    }

    /**
     * Menú móvil: solo enlaces principales
     */
    public static function mobileLinks(): array
    {
        $structure = static::structure();
        $links = [];
        foreach ($structure as $group) {
            $items = array_values(array_filter($group['items']));
            foreach ($items as $item) {
                $links[] = [
                    'label' => $item['label'],
                    'route' => $item['route'],
                    'active' => $item['active'],
                ];
            }
        }

        return $links;
    }
}
