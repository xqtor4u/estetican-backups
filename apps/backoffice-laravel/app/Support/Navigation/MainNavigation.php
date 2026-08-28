<?php

namespace App\Support\Navigation;

use App\Support\Navigation\Groups\CatalogsNavigation;
use App\Support\Navigation\Groups\ClientsNavigation;
use App\Support\Navigation\Groups\FinanzasNavigation;
use App\Support\Navigation\Groups\HrNavigation;
use App\Support\Navigation\Groups\InventoryNavigation;
use App\Support\Navigation\Groups\OperationsNavigation;
use App\Support\Navigation\Groups\ReportesNavigation;
use App\Support\Navigation\Groups\VeterinariaNavigation;
use App\Support\Navigation\Groups\WhatsAppNavigation;

class MainNavigation
{
    /**
     * Estructura única de navegación para ambos menús.
     * RH (Operadores/Tipos de operador/Usuarios) tiene su propia pestaña de nivel superior —
     * antes vivía como sub-sección dentro de "Administración", a pedido del usuario (03/08/2026)
     * se separó para no mezclarse con temas de negocio. "Operaciones del negocio" agrupa lo que
     * queda de la antigua "Administración" (Inventario/Finanzas) como sub-secciones dentro de un
     * solo dropdown — distinto de "Operación" (Agenda/Recursos/Mapa/Config), que es de uso
     * diario. "Reportes" es pestaña nueva, arranca solo con Bitácora de actividad (movida desde
     * Catálogos, encaja mejor temáticamente ahí) hasta que se construya BL-008. Clientes (temas
     * de clientes/mascotas/sucursales) se queda como persiana propia de nivel superior — es de
     * uso más frecuente y el usuario pidió no mezclarla con el resto. "Veterinaria" salió de
     * "Operaciones del negocio" y pasó a pestaña propia de nivel superior (16/08/2026, a pedido
     * del usuario) — junto con las pestañas nuevas en la ficha de mascota, deja de sentirse como
     * un módulo secundario anidado y pasa a ser un flujo de trabajo de primera clase.
     */
    public static function structure(): array
    {
        return [
            ClientsNavigation::group(),
            VeterinariaNavigation::group(),
            OperationsNavigation::group(),
            CatalogsNavigation::group(),
            HrNavigation::group(),
            static::operacionesDelNegocioGroup(),
            ReportesNavigation::group(),
            WhatsAppNavigation::group(),
        ];
    }

    private static function operacionesDelNegocioGroup(): array
    {
        return [
            'label' => 'Operaciones del negocio',
            'subgroups' => [
                InventoryNavigation::group(),
                FinanzasNavigation::group(),
            ],
        ];
    }

    /**
     * Menú de escritorio: grupos con items y descripciones.
     * Soporta grupos planos (`items`) y grupos anidados (`subgroups`, ej. Administración).
     */
    public static function groups(): array
    {
        $structure = static::structure();

        foreach ($structure as &$group) {
            if (isset($group['subgroups'])) {
                foreach ($group['subgroups'] as &$subgroup) {
                    $subgroup['items'] = array_values(array_filter($subgroup['items']));
                }
                unset($subgroup);

                $group['subgroups'] = array_values(array_filter(
                    $group['subgroups'],
                    fn ($subgroup) => ! empty($subgroup['items'])
                ));
                $group['active'] = collect($group['subgroups'])->contains(fn ($subgroup) => $subgroup['active']);
            } else {
                $group['items'] = array_values(array_filter($group['items']));
                $group['active'] = collect($group['items'])->contains(fn ($item) => $item['active']);
            }
        }
        unset($group);

        // Oculta grupos completos sin ningún item visible (ej. Veterinaria con el módulo apagado,
        // o Administración entera si ninguna de sus sub-secciones tiene items)
        return array_values(array_filter($structure, function ($group) {
            return isset($group['subgroups']) ? ! empty($group['subgroups']) : ! empty($group['items']);
        }));
    }

    /**
     * Menú móvil: solo enlaces principales (aplana también los grupos anidados)
     */
    public static function mobileLinks(): array
    {
        $structure = static::structure();
        $links = [];

        foreach ($structure as $group) {
            $itemGroups = isset($group['subgroups'])
                ? collect($group['subgroups'])->pluck('items')->all()
                : [$group['items']];

            foreach ($itemGroups as $items) {
                foreach (array_values(array_filter($items)) as $item) {
                    $links[] = [
                        'label' => $item['label'],
                        'route' => $item['route'],
                        'active' => $item['active'],
                    ];
                }
            }
        }

        return $links;
    }
}
