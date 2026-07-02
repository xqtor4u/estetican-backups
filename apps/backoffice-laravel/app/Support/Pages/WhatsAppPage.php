<?php

namespace App\Support\Pages;

use App\Models\WhatsAppTemplate;

class WhatsAppPage extends BasePage
{
    public static function bandeja(): array
    {
        return static::page(
            [static::home(), ['label' => 'Recordatorios WhatsApp', 'current' => true]],
            static::header('Comunicaciones', 'Bandeja diaria de recordatorios', 'Selecciona las citas del día y envía el recordatorio de WhatsApp con la plantilla elegida.'),
            'WspBan',
        );
    }

    public static function plantillasIndex(): array
    {
        return static::page(
            [
                static::home(),
                ['label' => 'Recordatorios WhatsApp', 'url' => route('whatsapp.bandeja')],
                ['label' => 'Plantillas', 'current' => true],
            ],
            static::header('Comunicaciones', 'Plantillas de mensaje', 'Mensajes predefinidos con variables para reutilizar en la bandeja diaria.'),
            'WspPlIdx',
        );
    }

    public static function plantillasCreate(): array
    {
        return static::page(
            [
                static::home(),
                ['label' => 'Recordatorios WhatsApp', 'url' => route('whatsapp.bandeja')],
                ['label' => 'Plantillas', 'url' => route('whatsapp.plantillas.index')],
                ['label' => 'Nueva plantilla', 'current' => true],
            ],
            static::header('Comunicaciones', 'Nueva plantilla', 'Define el texto y las variables que se reemplazarán al enviar.'),
            'WspPlCre',
        );
    }

    public static function plantillasEdit(WhatsAppTemplate $template): array
    {
        return static::page(
            [
                static::home(),
                ['label' => 'Recordatorios WhatsApp', 'url' => route('whatsapp.bandeja')],
                ['label' => 'Plantillas', 'url' => route('whatsapp.plantillas.index')],
                ['label' => $template->name, 'current' => true],
            ],
            static::header('Comunicaciones', 'Editar plantilla', 'Ajusta el texto y las variables de este mensaje predefinido.'),
            'WspPlEdi',
        );
    }
}
