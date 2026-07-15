<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\User;
use App\Support\SystemSettings\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SystemSettingsAssistantTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate('admin', 'web');

        $user = User::create([
            'name' => 'Admin Test',
            'first_name' => 'Admin',
            'apellido_paterno' => 'Test',
            'email' => 'admin-settings-assistant-test@example.com',
            'password' => bcrypt('secret'),
            'role' => 'admin',
        ]);
        $user->assignRole('admin');

        return $user;
    }

    public function test_saving_assistant_settings_is_readable_via_all(): void
    {
        $this->actingAs($this->admin())
            ->put(route('system-settings.update', 'ai_assistant'), [
                'ai_assistant_enabled' => '1',
                'ai_assistant_api_key' => 'sk-ant-test-key',
                'ai_assistant_model' => 'claude-haiku-4-5-20251001',
                'ai_assistant_cta_label' => 'Agenda tu cita',
                'ai_assistant_cta_url' => 'https://wa.me/5215500000000',
                'ai_assistant_site_token' => 'token-secreto',
                'ai_assistant_allowed_origin' => 'https://www.estetican.org',
            ]);

        $all = app(SystemSettings::class)->all();

        $this->assertTrue($all['ai_assistant_enabled']);
        $this->assertSame('sk-ant-test-key', $all['ai_assistant_api_key']);
        $this->assertSame('claude-haiku-4-5-20251001', $all['ai_assistant_model']);
        $this->assertSame('https://wa.me/5215500000000', $all['ai_assistant_cta_url']);
        $this->assertSame('token-secreto', $all['ai_assistant_site_token']);
        $this->assertSame('https://www.estetican.org', $all['ai_assistant_allowed_origin']);
    }

    public function test_assistant_api_key_is_stored_encrypted_not_in_plain_text(): void
    {
        $this->actingAs($this->admin())
            ->put(route('system-settings.update', 'ai_assistant'), [
                'ai_assistant_api_key' => 'sk-ant-test-key',
                'ai_assistant_model' => 'claude-haiku-4-5-20251001',
            ]);

        $this->assertDatabaseMissing('system_settings', [
            'key' => 'ai_assistant_api_key',
            'value' => 'sk-ant-test-key',
        ]);

        $stored = SystemSetting::where('key', 'ai_assistant_api_key')->first();
        $this->assertNotNull($stored);
        $this->assertSame('sk-ant-test-key', Crypt::decryptString($stored->value));
    }

    public function test_assistant_api_key_is_not_wiped_when_the_section_is_saved_again_blank(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('system-settings.update', 'ai_assistant'), [
            'ai_assistant_api_key' => 'sk-ant-test-key',
            'ai_assistant_model' => 'claude-haiku-4-5-20251001',
        ]);

        $this->actingAs($admin)->put(route('system-settings.update', 'ai_assistant'), [
            'ai_assistant_api_key' => '',
            'ai_assistant_model' => 'claude-sonnet-5',
        ]);

        $all = app(SystemSettings::class)->all();

        $this->assertSame('claude-sonnet-5', $all['ai_assistant_model']);
        $this->assertSame('sk-ant-test-key', $all['ai_assistant_api_key']);
    }
}
