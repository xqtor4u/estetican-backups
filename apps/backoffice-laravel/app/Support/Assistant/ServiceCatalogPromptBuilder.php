<?php

namespace App\Support\Assistant;

use App\Domain\Catalog\Contracts\ServiceCatalogServiceInterface;
use App\Models\Item;
use App\Support\SystemSettings\SystemSettings;

class ServiceCatalogPromptBuilder
{
    private const TYPE_LABELS = [
        'spa' => 'Spa / Estética',
        'hotel' => 'Hospedaje',
        'extra' => 'Extra',
        'combo' => 'Combo',
        'vaccine' => 'Vacuna',
    ];

    public function __construct(
        private ServiceCatalogServiceInterface $catalog,
        private SystemSettings $settings,
    ) {}

    public function build(): string
    {
        $settings = $this->settings->all();
        $businessName = (string) ($settings['brand_business_name'] ?? 'EstetiCAN');
        $servicesBlock = $this->buildServicesBlock();
        $itemsBlock = $this->buildItemsBlock();

        $prompt = <<<PROMPT
        Eres el asistente virtual del sitio web de {$businessName}, un spa y hotel para mascotas. Tu única función es responder preguntas de visitantes sobre los servicios y artículos que ofrece el negocio, usando exclusivamente la información que se te da a continuación.

        Reglas estrictas:
        - No inventes servicios, artículos, precios ni duraciones que no estén en las listas de abajo. Si no tienes la información, dilo con honestidad.
        - No agendes citas ni tomes datos personales — si alguien quiere agendar o hacer una cita, invítalo a usar el botón de la interfaz, no lo hagas dentro del chat.
        - Un servicio marcado "sin precio publicado" es genérico: confirma que sí se ofrece, pero aclara que el precio y alcance exacto se define en una cita de evaluación — invita a agendar por el botón.
        - Un servicio marcado "[URGENCIA]" requiere contacto inmediato: si el visitante describe una situación de urgencia, invítalo de inmediato a usar el botón de WhatsApp de la interfaz en vez de seguir la conversación en el chat.
        - Un artículo solo aparece en la lista si hay existencia disponible ahora mismo — si no está en la lista, no lo ofrezcas ni digas que hay existencia.
        - Mantén un tono cordial y breve, en español.
        - Si preguntan algo fuera de tema (no relacionado a los servicios o artículos del negocio), redirige amablemente la conversación de vuelta al catálogo.

        Catálogo de servicios:
        {$servicesBlock}

        Artículos disponibles:
        {$itemsBlock}
        PROMPT;

        $extra = trim((string) ($settings['ai_assistant_extra_prompt'] ?? ''));

        if ($extra !== '') {
            $prompt .= "\n\nInstrucciones adicionales del negocio:\n{$extra}";
        }

        return $prompt;
    }

    private function buildServicesBlock(): string
    {
        $services = $this->catalog->getAssistantVisibleServices();

        if ($services->isEmpty()) {
            return 'No hay servicios visibles para el asistente todavía.';
        }

        return $services
            ->map(function ($service) {
                $type = self::TYPE_LABELS[$service->type] ?? $service->type;
                $description = trim((string) $service->description) ?: 'sin descripción adicional';
                $duration = $service->duration_minutes ? "{$service->duration_minutes} min" : 'no especificada';
                $price = $service->is_generic
                    ? 'sin precio publicado'
                    : ('$' . number_format((float) $service->price, 2) . ' MXN');
                $tag = $service->is_emergency ? ' [URGENCIA]' : '';

                return "- {$service->name} ({$type}){$tag} — {$price}, duración {$duration}. {$description}";
            })
            ->join("\n");
    }

    private function buildItemsBlock(): string
    {
        $items = Item::query()
            ->where('is_active', true)
            ->where('ai_visible', true)
            ->where('stock_quantity', '>', 0)
            ->get();

        if ($items->isEmpty()) {
            return 'No hay artículos disponibles para mostrar todavía.';
        }

        return $items
            ->map(function ($item) {
                $details = collect([trim((string) $item->brand), trim((string) $item->presentation)])
                    ->filter()
                    ->join(', ');
                $price = $item->price !== null
                    ? ('$' . number_format((float) $item->price, 2) . ' MXN')
                    : 'precio a consultar';
                $department = $item->department ? " ({$item->department})" : '';

                return "- {$item->name}{$department} — {$price}" . ($details !== '' ? " — {$details}" : '');
            })
            ->join("\n");
    }
}
