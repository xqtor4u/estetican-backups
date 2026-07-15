<?php

namespace App\Support\Navigation\Groups;

class ClientsNavigation
{
    /**
     * @return array<string, mixed>
     */
    public static function group(): array
    {
        $user = auth()->user();
        $items = [];

        if ($user?->can('ver clientes') || $user?->is_super_admin) {
            $items[] = [
                'label' => 'Abrir clientes',
                'description' => 'Listado general y entrada principal al módulo.',
                'route' => route('clients.index'),
                'active' => request()->routeIs('clients.index', 'clients.show'),
            ];
        }

        if ($user?->can('ver mascotas') || $user?->is_super_admin) {
            $items[] = [
                'label' => 'Mascotas',
                'description' => 'Listado raíz de mascotas y acceso a detalle.',
                'route' => route('pets.index'),
                'active' => request()->routeIs('pets.*', 'clients.pets.show'),
            ];
        }

        if ($user?->can('ver sucursales') || $user?->is_super_admin) {
            $items[] = [
                'label' => 'Sucursales',
                'description' => 'Catálogo de bases operativas para asignación controlada de personal y futura operación multisucursal.',
                'route' => route('branches.index'),
                'active' => request()->routeIs('branches.index', 'branches.show'),
            ];
        }

        return [
            'label' => 'Clientes',
            'active' => request()->routeIs('clients.*', 'pets.*', 'branches.*'),
            'items' => $items,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function mobileLinks(): array
    {
        return [
            [
                'label' => 'Clientes',
                'route' => route('clients.index'),
                'active' => request()->routeIs('clients.index', 'clients.show'),
            ],
            [
                'label' => 'Mascotas',
                'route' => route('pets.index'),
                'active' => request()->routeIs('pets.*', 'clients.pets.show'),
            ],
        ];
    }
}