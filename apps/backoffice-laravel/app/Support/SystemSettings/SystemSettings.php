<?php

namespace App\Support\SystemSettings;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Throwable;

class SystemSettings
{
    private const CACHE_KEY = 'backoffice.system-settings.values';

    private static ?bool $settingsTableExists = null;

    /**
     * @return array<string, array<string, mixed>>
     */
    public function sections(): array
    {
        $values = $this->all();
        $sections = [];

        foreach ($this->definitions() as $sectionKey => $sectionDefinition) {
            $fields = [];

            foreach ($sectionDefinition['fields'] as $fieldKey => $fieldDefinition) {
                $selectedOption = null;

                if (! empty($fieldDefinition['options'])) {
                    foreach ($fieldDefinition['options'] as $option) {
                        if ((string) $option['value'] === (string) $values[$fieldKey]) {
                            $selectedOption = $option;
                            break;
                        }
                    }
                }

                $fields[$fieldKey] = [
                    ...$fieldDefinition,
                    'name' => $fieldKey,
                    'value' => $values[$fieldKey],
                    'selectedOption' => $selectedOption,
                ];
            }

            $sections[$sectionKey] = [
                'key' => $sectionKey,
                'label' => $sectionDefinition['label'],
                'description' => $sectionDefinition['description'],
                'fields' => $fields,
            ];
        }

        return $sections;
    }

    public function hasSection(string $section): bool
    {
        return array_key_exists($section, $this->definitions());
    }

    public function labelFor(string $section): string
    {
        return $this->definitions()[$section]['label'] ?? $section;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function paletteDefinitions(): array
    {
        return (array) config('backoffice.ui.palettes', []);
    }

    public function paletteLabel(string $palette): string
    {
        return data_get($this->paletteDefinitions(), $palette.'.label', $palette);
    }

    /**
     * @return array<string, mixed>
     */
    public function rulesFor(string $section): array
    {
        $sectionDefinition = $this->definitions()[$section] ?? null;

        if (! $sectionDefinition) {
            return [];
        }

        $rules = [];

        foreach ($sectionDefinition['fields'] as $fieldKey => $fieldDefinition) {
            $rules[$fieldKey] = $fieldDefinition['rules'];
        }

        return $rules;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $storedValues = $this->storedValues();
        $resolvedValues = [];

        foreach ($this->definitions() as $sectionDefinition) {
            foreach ($sectionDefinition['fields'] as $fieldKey => $fieldDefinition) {
                $resolvedValues[$fieldKey] = array_key_exists($fieldKey, $storedValues)
                    ? $this->castValue($fieldDefinition, $storedValues[$fieldKey])
                    : $fieldDefinition['default'];
            }
        }

        return $resolvedValues;
    }

    /**
     * @return array<string, mixed>
     */
    public function configOverrides(): array
    {
        $values = $this->all();
        $overrides = [];

        foreach ($this->definitions() as $sectionDefinition) {
            foreach ($sectionDefinition['fields'] as $fieldKey => $fieldDefinition) {
                if (! isset($fieldDefinition['config'])) {
                    continue;
                }

                $overrides[$fieldDefinition['config']] = $values[$fieldKey];
            }
        }

        return $overrides;
    }

    public function save(string $section, array $validated): void
    {
        $sectionDefinition = $this->definitions()[$section] ?? null;

        if (! $sectionDefinition || ! $this->hasSettingsTable()) {
            return;
        }

        $timestamp = now();
        $payload = [];
        $stored = $this->storedValues();

        foreach ($sectionDefinition['fields'] as $fieldKey => $fieldDefinition) {
            $incoming = $validated[$fieldKey] ?? null;

            // Los campos tipo password se dejan en blanco en el formulario a propósito
            // (nunca se re-imprime el valor guardado) — si llega vacío, no se toca lo
            // ya guardado, para no borrar la contraseña cada vez que se guarda la sección.
            if ($fieldDefinition['type'] === 'password' && ($incoming === null || $incoming === '')) {
                $payload[] = [
                    'section' => $section,
                    'key' => $fieldKey,
                    'type' => $fieldDefinition['type'],
                    'value' => $stored[$fieldKey] ?? null,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];

                continue;
            }

            $payload[] = [
                'section' => $section,
                'key' => $fieldKey,
                'type' => $fieldDefinition['type'],
                'value' => $this->serializeValue($fieldDefinition, $incoming),
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }

        SystemSetting::query()->upsert($payload, ['key'], ['section', 'type', 'value', 'updated_at']);

        static::flushCache();
    }

    /**
     * Guarda solo los campos indicados (actualización parcial de una sección).
     * Los campos no incluidos en $validated no se tocan.
     *
     * @param  array<string, mixed>  $validated
     */
    public function saveFields(string $section, array $validated): void
    {
        $sectionDefinition = $this->definitions()[$section] ?? null;

        if (! $sectionDefinition || ! $this->hasSettingsTable()) {
            return;
        }

        $timestamp = now();
        $payload = [];

        foreach ($validated as $fieldKey => $value) {
            $fieldDefinition = $sectionDefinition['fields'][$fieldKey] ?? null;

            if (! $fieldDefinition) {
                continue;
            }

            $payload[] = [
                'section' => $section,
                'key' => $fieldKey,
                'type' => $fieldDefinition['type'],
                'value' => $this->serializeValue($fieldDefinition, $value),
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }

        if (! empty($payload)) {
            SystemSetting::query()->upsert($payload, ['key'], ['section', 'type', 'value', 'updated_at']);
            static::flushCache();
        }
    }

    public static function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, string|null>
     */
    private function storedValues(): array
    {
        if (! $this->hasSettingsTable()) {
            return [];
        }

        try {
            return Cache::remember(self::CACHE_KEY, now()->addMinutes(30), static function (): array {
                return SystemSetting::query()->pluck('value', 'key')->all();
            });
        } catch (Throwable) {
            return [];
        }
    }

    private function hasSettingsTable(): bool
    {
        if (static::$settingsTableExists !== null) {
            return static::$settingsTableExists;
        }

        try {
            static::$settingsTableExists = Schema::hasTable('system_settings');
        } catch (Throwable) {
            static::$settingsTableExists = false;
        }

        return static::$settingsTableExists;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function definitions(): array
    {
        return [
            'ui' => [
                'label' => 'Visualización',
                'description' => 'Controla branding, etiquetas de shell y densidad visual.',
                'fields' => [
                    'brand_html_title' => [
                        'label' => 'Título HTML',
                        'type' => 'text',
                        'default' => (string) config('backoffice.brand.html_title', 'EstetiCAN Backoffice'),
                        'config' => 'backoffice.brand.html_title',
                        'rules' => ['required', 'string', 'max:120'],
                        'help' => 'Se usa en la pestaña del navegador.',
                    ],
                    'ui_density' => [
                        'label' => 'Densidad visual',
                        'type' => 'select',
                        'default' => 'comfortable',
                        'config' => 'backoffice.ui.density',
                        'rules' => ['required', Rule::in(['comfortable', 'compact'])],
                        'options' => [
                            ['value' => 'comfortable', 'label' => 'Cómoda'],
                            ['value' => 'compact', 'label' => 'Compacta'],
                        ],
                    ],
                    'ui_color_palette' => [
                        'label' => 'Paleta visual',
                        'type' => 'select',
                        'default' => 'earth-clinic',
                        'config' => 'backoffice.ui.palette',
                        'rules' => ['required', Rule::in(array_keys($this->paletteDefinitions()))],
                        'options' => $this->paletteOptions(),
                    ],
                ],
            ],
            'branding' => [
                'label' => 'Identidad y Branding',
                'description' => 'Personaliza el nombre y logos de la clínica.',
                'fields' => [
                    'brand_business_name' => [
                        'label' => 'Nombre de la Estética / Clínica',
                        'type' => 'text',
                        'default' => 'EstetiCAN',
                        'config' => 'backoffice.brand.kicker',
                        'rules' => ['required', 'string', 'max:100'],
                    ],
                    'brand_logo_web' => [
                        'label' => 'Logo para Interfaz (Web)',
                        'type' => 'image',
                        'default' => null,
                        'config' => 'backoffice.brand.logo',
                        'rules' => ['nullable', 'string'],
                    ],
                    'brand_favicon' => [
                        'label' => 'Favicon',
                        'type' => 'image',
                        'default' => null,
                        'config' => 'backoffice.brand.favicon',
                        'rules' => ['nullable', 'string'],
                    ],
                    'brand_whatsapp_number' => [
                        'label' => 'WhatsApp del negocio (con lada, sin espacios)',
                        'type' => 'text',
                        'default' => '',
                        'rules' => ['nullable', 'string', 'max:20'],
                        'help' => 'Ej. 5215512345678. Se usa como botón de contacto en los correos enviados a clientes.',
                    ],
                ],
            ],
            'guarantees' => [
                'label' => 'Garantías y Anticipos',
                'description' => 'Reglas financieras para servicios que requieren compromiso previo (Hotel, Cirugías, Tienda, etc.).',
                'fields' => [
                    'service_advance_percentage' => [
                        'label' => 'Anticipo predefinido (%)',
                        'type' => 'number',
                        'default' => 30,
                        'rules' => ['required', 'integer', 'min:0', 'max:100'],
                        'input' => ['min' => 0, 'max' => 100, 'step' => 5],
                        'help' => 'Porcentaje sugerido para servicios marcados con "Requiere Anticipo".',
                    ],
                    'service_penalty_percentage' => [
                        'label' => 'Penalización por cancelación (%)',
                        'type' => 'number',
                        'default' => 20,
                        'rules' => ['required', 'integer', 'min:0', 'max:100'],
                        'input' => ['min' => 0, 'max' => 100, 'step' => 5],
                        'help' => 'Monto retenido si se cancela fuera de la ventana permitida.',
                    ],
                    'service_cancellation_window_hours' => [
                        'label' => 'Ventana de cancelación sin cargo (horas)',
                        'type' => 'number',
                        'default' => 24,
                        'rules' => ['required', 'integer', 'min:0'],
                        'help' => 'Tiempo límite para cancelar sin perder el anticipo.',
                    ],
                    'allow_override_advance_requirement' => [
                        'label' => 'Permitir omitir anticipos obligatorios',
                        'type' => 'boolean',
                        'default' => true,
                        'rules' => ['nullable', 'boolean'],
                        'help' => 'Si se activa, el usuario podrá dejar el anticipo en $0 aunque el servicio lo requiera.',
                    ],
                ],
            ],
            'clinical' => [
                'label' => 'Operación Clínica',
                'description' => 'Configuración de Bitácoras y Jaulas.',
                'fields' => [
                    'operational_auto_email_report' => [
                        'label' => 'Enviar resumen por correo al finalizar atención',
                        'type' => 'boolean',
                        'default' => false,
                        'rules' => ['nullable', 'boolean'],
                        'help' => 'Envía automáticamente la bitácora de servicio al correo del cliente al marcar como "Completado".',
                    ],
                    'system_resource_cleaning_buffer_minutes' => [
                        'label' => 'Minutos de limpieza entre usos',
                        'type' => 'number',
                        'default' => 15,
                        'rules' => ['required', 'integer', 'min:0', 'max:240'],
                    ],
                    'operational_auto_unblock_on_complete' => [
                        'label' => 'Liberar jaula automáticamente al finalizar',
                        'type' => 'boolean',
                        'default' => true,
                        'rules' => ['nullable', 'boolean'],
                    ],
                    'booking_grace_minutes' => [
                        'label' => 'Tolerancia para iniciar servicio (minutos)',
                        'type' => 'number',
                        'default' => 15,
                        'rules' => ['required', 'integer', 'min:0', 'max:120'],
                        'input' => ['min' => 0, 'max' => 120, 'step' => 5],
                        'help' => 'Margen permitido antes o después de la hora programada. Si el operador inicia fuera de este rango, la app móvil pedirá confirmación.',
                    ],
                    'booking_opening_time' => [
                        'label' => 'Hora de apertura',
                        'type' => 'text',
                        'default' => '09:00',
                        'rules' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
                        'help' => 'Formato 24h HH:MM. Aplica todos los días. Usado para validar que las citas se agenden dentro del horario operativo.',
                    ],
                    'booking_closing_time' => [
                        'label' => 'Hora de cierre',
                        'type' => 'text',
                        'default' => '19:00',
                        'rules' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/', 'after:booking_opening_time'],
                    ],
                ],
            ],
            'fiscal' => [
                'label' => 'Datos Fiscales',
                'description' => 'Información legal para reportes.',
                'fields' => [
                    'fiscal_legal_name' => [
                        'label' => 'Razón Social',
                        'type' => 'text',
                        'default' => '',
                        'rules' => ['nullable', 'string', 'max:200'],
                    ],
                    'fiscal_id' => [
                        'label' => 'RFC / Identificación Fiscal',
                        'type' => 'text',
                        'default' => '',
                        'rules' => ['nullable', 'string', 'max:50'],
                    ],
                    'fiscal_address' => [
                        'label' => 'Dirección Fiscal',
                        'type' => 'text',
                        'default' => '',
                        'rules' => ['nullable', 'string', 'max:300'],
                        'help' => 'Aparece en presupuestos, órdenes de trabajo y recibos.',
                    ],
                    'fiscal_report_footer' => [
                        'label' => 'Pie de página en reportes',
                        'type' => 'text',
                        'default' => 'Gracias por su confianza.',
                        'rules' => ['nullable', 'string', 'max:200'],
                    ],
                    'mail_signature_url' => [
                        'label' => 'URL del negocio (para reportes y correos)',
                        'type' => 'text',
                        'default' => '',
                        'rules' => ['nullable', 'string', 'max:200'],
                    ],
                ],
            ],
            'email_service' => [
                'label' => 'Servicio de Correo',
                'description' => 'Configura el envío de correos SMTP.',
                'fields' => [
                    'mail_host' => [
                        'label' => 'Servidor SMTP',
                        'type' => 'text',
                        'default' => config('mail.mailers.smtp.host'),
                        'config' => 'mail.mailers.smtp.host',
                        'rules' => ['nullable', 'string'],
                    ],
                    'mail_port' => [
                        'label' => 'Puerto',
                        'type' => 'number',
                        'default' => config('mail.mailers.smtp.port'),
                        'config' => 'mail.mailers.smtp.port',
                        'rules' => ['nullable', 'integer'],
                    ],
                    'mail_username' => [
                        'label' => 'Usuario SMTP',
                        'type' => 'text',
                        'default' => config('mail.mailers.smtp.username'),
                        'config' => 'mail.mailers.smtp.username',
                        'rules' => ['nullable', 'string', 'max:200'],
                    ],
                    'mail_password' => [
                        'label' => 'Contraseña SMTP',
                        'type' => 'password',
                        'default' => config('mail.mailers.smtp.password'),
                        'config' => 'mail.mailers.smtp.password',
                        'rules' => ['nullable', 'string', 'max:200'],
                        'help' => 'Se guarda cifrada. Dejar en blanco al editar la sección para conservar la contraseña ya guardada.',
                    ],
                    'mail_encryption' => [
                        // Symfony Mailer (el transporte SMTP real detrás de Laravel) solo
                        // reconoce los esquemas "smtp" y "smtps" para este mailer — "ssl"/"tls"
                        // como valores literales de scheme no son válidos y truenan en tiempo
                        // de envío ("The 'ssl' scheme is not supported…", ver NT-030).
                        'label' => 'Encriptación',
                        'type' => 'select',
                        'default' => in_array(config('mail.mailers.smtp.scheme'), ['smtp', 'smtps'], true)
                            ? config('mail.mailers.smtp.scheme')
                            : 'smtps',
                        'config' => 'mail.mailers.smtp.scheme',
                        'rules' => ['required', Rule::in(['smtp', 'smtps'])],
                        'options' => [
                            ['value' => 'smtps', 'label' => 'SSL/TLS implícito (puerto 465)'],
                            ['value' => 'smtp', 'label' => 'Ninguna / STARTTLS automático (puerto 587)'],
                        ],
                    ],
                    'mail_from_address' => [
                        'label' => 'Correo remitente',
                        'type' => 'text',
                        'default' => config('mail.from.address'),
                        'config' => 'mail.from.address',
                        'rules' => ['nullable', 'email', 'max:200'],
                    ],
                    'mail_from_name' => [
                        'label' => 'Nombre remitente',
                        'type' => 'text',
                        'default' => config('mail.from.name'),
                        'config' => 'mail.from.name',
                        'rules' => ['nullable', 'string', 'max:200'],
                    ],
                ],
            ],
            'system' => [
                'label' => 'Sistema',
                'description' => 'Zona horaria, moneda y formatos.',
                'fields' => [
                    'system_timezone' => [
                        'label' => 'Zona horaria',
                        'type' => 'text',
                        'default' => 'America/Mexico_City',
                        'rules' => ['required', 'timezone'],
                    ],
                    'system_currency_code' => [
                        'label' => 'Moneda base',
                        'type' => 'text',
                        'default' => 'MXN',
                        'rules' => ['required', 'string', 'size:3'],
                    ],
                    'system_time_format' => [
                        'label' => 'Formato de hora',
                        'type' => 'select',
                        'default' => '12h',
                        'config' => 'backoffice.system.time_format',
                        'rules' => ['required', Rule::in(['12h', '24h'])],
                        'options' => [
                            ['value' => '12h', 'label' => '12 horas (AM/PM)'],
                            ['value' => '24h', 'label' => '24 horas'],
                        ],
                    ],
                ],
            ],
            'media' => [
                'label' => 'Fotografías',
                'description' => 'Reglas para fotos subidas desde la app móvil (perfil de usuario, mascotas, etc.).',
                'fields' => [
                    'photo_watermark_enabled' => [
                        'label' => 'Marca de agua en fotos subidas',
                        'type' => 'boolean',
                        'default' => false,
                        'rules' => ['nullable', 'boolean'],
                        'help' => 'Si está activo, las fotos subidas desde la app móvil llevan una marca de agua pequeña (nombre y fecha) en la parte inferior, agregada antes de subir la foto.',
                    ],
                ],
            ],
            'finanzas' => [
                'label' => 'Finanzas',
                'description' => 'Configuración del módulo contable, caja y documentos.',
                'fields' => [
                    'finanzas_requiere_apertura_caja' => [
                        'label' => 'Requerir apertura de caja para cobrar',
                        'type' => 'boolean',
                        'default' => false,
                        'help' => 'Si está activo, el operador debe abrir una sesión de caja antes de registrar cobros.',
                        'rules' => ['nullable', 'boolean'],
                    ],
                    'finanzas_asientos_auto_aplicar' => [
                        'label' => 'Aplicar asientos automáticamente',
                        'type' => 'boolean',
                        'default' => true,
                        'help' => 'Si está activo, los asientos se aplican al confirmar el cobro sin requerir aprobación adicional.',
                        'rules' => ['nullable', 'boolean'],
                    ],
                    'finanzas_moneda' => [
                        'label' => 'Moneda de operación',
                        'type' => 'text',
                        'default' => 'MXN',
                        'rules' => ['required', 'string', 'size:3'],
                    ],
                ],
            ],
        ];
    }

    private function castValue(array $fieldDefinition, mixed $value): mixed
    {
        if ($fieldDefinition['type'] === 'boolean') {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }
        if ($fieldDefinition['type'] === 'number') {
            return (int) $value;
        }
        if ($fieldDefinition['type'] === 'password') {
            if ($value === null || $value === '') {
                return null;
            }

            try {
                return Crypt::decryptString($value);
            } catch (Throwable) {
                // Valor legado sin cifrar o corrupto — se trata como no configurado.
                return null;
            }
        }
        if ($fieldDefinition['type'] === 'select' && ! empty($fieldDefinition['options'])) {
            $validValues = array_map(fn ($option) => (string) $option['value'], $fieldDefinition['options']);

            // Si las opciones válidas de un campo cambiaron entre versiones (ver
            // NT-030 — "ssl"/"tls" dejaron de ser valores válidos de encriptación),
            // un valor viejo guardado en la BD ya no se propaga indefinidamente —
            // se cae al default en vez de un valor obsoleto que ya no existe.
            if (! in_array((string) $value, $validValues, true)) {
                return $fieldDefinition['default'];
            }
        }

        return is_string($value) ? trim($value) : $value;
    }

    private function serializeValue(array $fieldDefinition, mixed $value): ?string
    {
        if ($fieldDefinition['type'] === 'boolean') {
            return ! empty($value) ? '1' : '0';
        }
        if ($fieldDefinition['type'] === 'number') {
            return (string) ((int) $value);
        }
        $normalized = is_string($value) ? trim($value) : $value;

        if ($fieldDefinition['type'] === 'password') {
            return $normalized === '' || $normalized === null ? null : Crypt::encryptString((string) $normalized);
        }

        return $normalized === '' ? null : (string) $normalized;
    }

    private function paletteOptions(): array
    {
        $options = [];
        foreach ($this->paletteDefinitions() as $paletteKey => $palette) {
            $options[] = [
                'value' => $paletteKey,
                'label' => (string) ($palette['label'] ?? $paletteKey),
                'description' => (string) ($palette['description'] ?? ''),
                'colors' => (array) ($palette['colors'] ?? []),
            ];
        }

        return $options;
    }
}
