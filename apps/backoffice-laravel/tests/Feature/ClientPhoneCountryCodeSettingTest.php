<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Support\SystemSettings\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

/**
 * México/Norteamérica exige 10 dígitos exactos por default (sin código de país). Configuración →
 * Clientes → "Permitir código de país en teléfonos" (nuevo, 20/08/2026) amplía el rango a 8-15
 * dígitos (estándar internacional E.164) para clínicas con clientes de zona fronteriza o de otro
 * país — ver App\Rules\ValidPhoneNumber::fromSettings().
 */
class ClientPhoneCountryCodeSettingTest extends TestCase
{
    use CreatesAdminUser;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->createAdminUser());
    }

    public function test_setting_defaults_to_off_and_exposes_exactly_ten_digits(): void
    {
        $headers = $this->createAdminAuthHeader();

        $response = $this->withHeaders($headers)->getJson('/api/settings/phone-format');

        $response->assertOk();
        $response->assertJson([
            'allow_country_code' => false,
            'min_digits' => 10,
            'max_digits' => 10,
        ]);
    }

    public function test_activating_the_setting_widens_the_public_endpoint_range(): void
    {
        app(SystemSettings::class)->saveFields('clients', ['commercial_clients_phone_allow_country_code' => true]);

        $headers = $this->createAdminAuthHeader();

        $response = $this->withHeaders($headers)->getJson('/api/settings/phone-format');

        $response->assertOk();
        $response->assertJson([
            'allow_country_code' => true,
            'min_digits' => 8,
            'max_digits' => 15,
        ]);
    }

    public function test_settings_page_exposes_the_new_clients_section(): void
    {
        $response = $this->get(route('system-settings.index'));

        $response->assertOk();
        $response->assertSee('Permitir código de país en teléfonos');
        $response->assertSee('E.164');
    }

    public function test_web_store_rejects_a_twelve_digit_number_by_default(): void
    {
        $response = $this->post(route('clients.store'), [
            'first_name' => 'ConLada',
            'apellido_paterno' => 'Prueba',
            'phones' => [
                ['type' => 'mobile', 'number' => '528112345678'],
            ],
        ]);

        $response->assertSessionHasErrors('phones.0.number');
    }

    public function test_web_store_accepts_a_twelve_digit_number_once_the_setting_is_on(): void
    {
        app(SystemSettings::class)->saveFields('clients', ['commercial_clients_phone_allow_country_code' => true]);

        $response = $this->post(route('clients.store'), [
            'first_name' => 'ConLada',
            'apellido_paterno' => 'Prueba',
            'phones' => [
                ['type' => 'mobile', 'number' => '528112345678'],
            ],
        ]);

        $response->assertRedirect(route('clients.index'));

        $client = Client::query()->where('first_name', 'ConLada')->firstOrFail();
        $this->assertSame('528112345678', $client->phones()->firstOrFail()->number);
    }

    public function test_api_store_rejects_a_twelve_digit_number_by_default(): void
    {
        $headers = $this->createAdminAuthHeader();

        $response = $this->withHeaders($headers)->postJson('/api/clients', [
            'first_name' => 'ApiConLada',
            'phones' => [
                ['type' => 'mobile', 'number' => '528112345678'],
            ],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('phones.0.number');
    }

    public function test_api_store_accepts_a_twelve_digit_number_once_the_setting_is_on(): void
    {
        app(SystemSettings::class)->saveFields('clients', ['commercial_clients_phone_allow_country_code' => true]);

        $headers = $this->createAdminAuthHeader();

        $response = $this->withHeaders($headers)->postJson('/api/clients', [
            'first_name' => 'ApiConLada',
            'phones' => [
                ['type' => 'mobile', 'number' => '528112345678'],
            ],
        ]);

        $response->assertCreated();
    }

    public function test_the_ten_digit_default_still_rejects_a_sixteen_digit_number_even_with_the_setting_on(): void
    {
        app(SystemSettings::class)->saveFields('clients', ['commercial_clients_phone_allow_country_code' => true]);

        $response = $this->post(route('clients.store'), [
            'first_name' => 'Excesivo',
            'apellido_paterno' => 'Prueba',
            'phones' => [
                ['type' => 'mobile', 'number' => '1234567890123456'],
            ],
        ]);

        $response->assertSessionHasErrors('phones.0.number');
    }
}
