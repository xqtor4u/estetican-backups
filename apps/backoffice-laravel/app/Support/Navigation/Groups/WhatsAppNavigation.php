<?php

namespace App\Support\Navigation\Groups;

class WhatsAppNavigation
{
    public static function group(): array
    {
        $user = auth()->user();
        $items = [];

        if ($user?->can('ver whatsapp') || $user?->is_super_admin) {
            $items[] = [
                'label' => 'Bandeja diaria',
                'description' => 'Citas del día con teléfono válido para enviar recordatorio por WhatsApp.',
                'route' => route('whatsapp.bandeja'),
                'debug_id' => 'WspBan',
                'active' => request()->routeIs('whatsapp.bandeja'),
            ];

            $items[] = [
                'label' => 'Plantillas de mensaje',
                'description' => 'Mensajes predefinidos con variables ({cliente}, {mascota}, {servicio}...).',
                'route' => route('whatsapp.plantillas.index'),
                'debug_id' => 'WspPlIdx',
                'active' => request()->routeIs('whatsapp.plantillas.*'),
            ];
        }

        return [
            'label' => 'WhatsApp',
            'active' => request()->routeIs('whatsapp.*'),
            'items' => $items,
        ];
    }
}
