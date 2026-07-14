<?php

namespace App\Support\Assistant;

use App\Domain\Catalog\Contracts\ServiceCatalogServiceInterface;
use App\Support\SystemSettings\SystemSettings;

class ServiceCatalogPromptBuilder
{
    private const TYPE_LABELS = [
        'spa' => 'Spa / Estética',
        'hotel' => 'Hospedaje',
        'extra' => 'Extra',
        'combo' => 'Combo',
    ];

    public function __construct(
        private ServiceCatalogServiceInterface $catalog,
        private SystemSettings $settings,
    ) {}

    public function build(): string
    {
        $settings = $this->settings->all();
        $businessName = (string) ($settings['brand_business_name'] ?? 'EstetiCAN');
        $catalogBlock = $this->buildCatalogBlock();

        $prompt = <<<PROMPT
        Eres el asistente virtual del sitio web de {$businessName}, un spa y hotel para mascotas. Tu única función es responder preguntas de visitantes sobre los servicios que ofrece el negocio, usando exclusivamente la información del catálogo que se te da a continuación.

        Reglas estrictas:
        - No inventes servicios, precios ni duraciones que no estén en el catálogo. Si no tienes la información, dilo con honestidad.
        - No agendes citas ni tomes datos personales — si alguien quiere agendar o hacer una cita, invítalo a usar el botón de la interfaz, no lo hagas dentro del chat.
        - Mantén un tono cordial y breve, en español.
        - Si preguntan algo fuera de tema (no relacionado a los servicios del negocio), redirige amablemente la conversación de vuelta a los servicios.

        Catálogo de servicios activos:
        {$catalogBlock}
        PROMPT;

        $extra = trim((string) ($settings['ai_assistant_extra_prompt'] ?? ''));

        if ($extra !== '') {
            $prompt .= "\n\nInstrucciones adicionales del negocio:\n{$extra}";
        }

        return $prompt;
    }

    private function buildCatalogBlock(): string
    {
        $services = $this->catalog->getActiveServices();

        if ($services->isEmpty()) {
            return 'No hay servicios activos cargados todavía.';
        }

        return $services
            ->map(function ($service) {
                $type = self::TYPE_LABELS[$service->type] ?? $service->type;
                $description = trim((string) $service->description) ?: 'sin descripción adicional';
                $price = number_format((float) $service->price, 2);
                $duration = $service->duration_minutes ? "{$service->duration_minutes} min" : 'no especificada';

                return "- {$service->name} ({$type}) — \${$price} MXN, duración {$duration}. {$description}";
            })
            ->join("\n");
    }
}
