<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\User;
use App\Support\SystemSettings\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SystemSettingsEmailTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate('admin', 'web');

        $user = User::create([
            'name' => 'Admin Test',
            'first_name' => 'Admin',
            'last_name' => 'Test',
            'email' => 'admin-settings-email-test@example.com',
            'password' => bcrypt('secret'),
            'role' => 'admin',
        ]);
        $user->assignRole('admin');

        return $user;
    }

    public function test_saving_smtp_settings_bridges_to_laravel_mail_config(): void
    {
        $response = $this->actingAs($this->admin())
            ->put(route('system-settings.update', 'email_service'), [
                'mail_host' => 'smtp.estetican.org',
                'mail_port' => 465,
                'mail_username' => 'no-reply@estetican.org',
                'mail_password' => 'super-secreta',
                'mail_encryption' => 'smtps',
                'mail_from_address' => 'no-reply@estetican.org',
                'mail_from_name' => 'EstetiCAN',
            ]);

        $response->assertRedirect();

        $overrides = app(SystemSettings::class)->configOverrides();

        $this->assertSame('smtp.estetican.org', $overrides['mail.mailers.smtp.host']);
        $this->assertSame(465, $overrides['mail.mailers.smtp.port']);
        $this->assertSame('no-reply@estetican.org', $overrides['mail.mailers.smtp.username']);
        $this->assertSame('super-secreta', $overrides['mail.mailers.smtp.password']);
        $this->assertSame('smtps', $overrides['mail.mailers.smtp.scheme']);
        $this->assertSame('no-reply@estetican.org', $overrides['mail.from.address']);
        $this->assertSame('EstetiCAN', $overrides['mail.from.name']);
    }

    public function test_smtp_password_is_stored_encrypted_not_in_plain_text(): void
    {
        $this->actingAs($this->admin())
            ->put(route('system-settings.update', 'email_service'), [
                'mail_host' => 'smtp.estetican.org',
                'mail_port' => 465,
                'mail_password' => 'super-secreta',
                'mail_encryption' => 'smtps',
            ]);

        $this->assertDatabaseMissing('system_settings', [
            'key' => 'mail_password',
            'value' => 'super-secreta',
        ]);

        $stored = SystemSetting::where('key', 'mail_password')->first();
        $this->assertNotNull($stored);
        $this->assertSame('super-secreta', Crypt::decryptString($stored->value));
    }

    public function test_smtp_password_is_not_wiped_when_the_section_is_saved_again_blank(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('system-settings.update', 'email_service'), [
            'mail_host' => 'smtp.estetican.org',
            'mail_port' => 465,
            'mail_password' => 'super-secreta',
            'mail_encryption' => 'smtps',
        ]);

        // Se vuelve a guardar la sección sin llenar el campo de contraseña
        // (como haría el formulario real, que nunca reimprime el valor guardado).
        $this->actingAs($admin)->put(route('system-settings.update', 'email_service'), [
            'mail_host' => 'smtp.estetican.org',
            'mail_port' => 587,
            'mail_password' => '',
            'mail_encryption' => 'smtps',
        ]);

        $overrides = app(SystemSettings::class)->configOverrides();

        $this->assertSame(587, $overrides['mail.mailers.smtp.port']);
        $this->assertSame('super-secreta', $overrides['mail.mailers.smtp.password']);
    }
}
