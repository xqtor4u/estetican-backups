<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\SystemSettings\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SystemSettingsTimezoneTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate('admin', 'web');

        $user = User::create([
            'name' => 'Admin Test',
            'first_name' => 'Admin',
            'apellido_paterno' => 'Test',
            'email' => 'admin-settings-timezone-test@example.com',
            'password' => bcrypt('secret'),
            'role' => 'admin',
        ]);
        $user->assignRole('admin');

        return $user;
    }

    public function test_settings_page_renders_a_real_timezone_select_not_a_free_text_input(): void
    {
        $response = $this->actingAs($this->admin())->get(route('system-settings.index'));

        $response->assertOk();
        $response->assertSee('America/Mexico_City', false);

        $html = $response->getContent();
        $this->assertStringContainsString('<select', $html);
        $this->assertMatchesRegularExpression('/<select[^>]*id="system_timezone"/', $html);
        $this->assertDoesNotMatchRegularExpression('/<input[^>]*id="system_timezone"/', $html);
    }

    public function test_saving_a_real_timezone_identifier_persists_and_applies_via_config_overrides(): void
    {
        $this->actingAs($this->admin())
            ->patch(route('system-settings.patch-field', 'system'), [
                'system_timezone' => 'America/Tijuana',
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $overrides = app(SystemSettings::class)->configOverrides();

        $this->assertSame('America/Tijuana', $overrides['backoffice.system.timezone']);
    }

    public function test_an_invalid_timezone_identifier_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->patch(route('system-settings.patch-field', 'system'), [
                'system_timezone' => 'Not/A_Real_Zone',
            ])
            ->assertStatus(422);
    }

    public function test_timezone_options_cover_all_php_supported_identifiers_with_real_utc_offsets(): void
    {
        $sections = app(SystemSettings::class)->sections();
        $options = collect($sections['system']['fields'])->firstWhere('name', 'system_timezone')['options'];

        $this->assertCount(count(\DateTimeZone::listIdentifiers(\DateTimeZone::ALL)), $options);

        $mexicoCity = collect($options)->firstWhere('value', 'America/Mexico_City');
        $this->assertNotNull($mexicoCity);
        $this->assertStringContainsString('UTC-06:00', $mexicoCity['label']);
    }
}
