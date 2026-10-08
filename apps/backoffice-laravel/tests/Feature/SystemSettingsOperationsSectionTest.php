<?php

namespace Tests\Feature;

use App\Support\SystemSettings\BusinessHours;
use App\Support\SystemSettings\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

/**
 * Regresión (07/10/2026): la sección "Operación Clínica" compartía la clave `clinical` con la de
 * "Veterinaria"; PHP se quedaba con la última y horario/tolerancia/limpieza/resumen por correo
 * dejaban de existir (desde el 14/07/2026, sin que fallara nada). Ahora es `operations`.
 */
class SystemSettingsOperationsSectionTest extends TestCase
{
    use CreatesAdminUser;
    use RefreshDatabase;

    public function test_operations_and_veterinary_settings_both_exist(): void
    {
        $all = app(SystemSettings::class)->all();

        foreach (['booking_opening_time', 'booking_closing_time', 'booking_grace_minutes', 'operational_auto_email_report', 'booking_open_sunday', 'clinical_module_enabled'] as $key) {
            $this->assertArrayHasKey($key, $all);
        }
    }

    public function test_opening_hours_saved_from_settings_are_applied(): void
    {
        $this->actingAs($this->createAdminUser(['is_super_admin' => true]));

        app(SystemSettings::class)->saveFields('operations', ['booking_opening_time' => '10:30']);

        $this->assertSame('10:30', app(BusinessHours::class)->openingTime());
        $this->get(route('system-settings.index'))->assertOk()->assertSee('Operación Clínica')->assertSee('Abre domingo');
    }
}
