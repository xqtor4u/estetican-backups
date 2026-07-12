<?php

namespace Tests\Feature\Api;

use App\Models\ApiToken;
use App\Models\User;
use App\Support\SystemSettings\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhotoSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function authHeader(): array
    {
        $user = User::create([
            'name' => 'Admin Test',
            'first_name' => 'Admin',
            'last_name' => 'Test',
            'email' => 'photo-settings-test@example.com',
            'password' => bcrypt('secret'),
            'role' => 'admin',
            'is_active' => true,
            'can_login' => true,
        ]);

        $plainToken = 'test-token-'.uniqid();
        ApiToken::create([
            'user_id' => $user->id,
            'token' => hash('sha256', $plainToken),
            'name' => 'test',
        ]);

        return ['Authorization' => "Bearer {$plainToken}"];
    }

    public function test_watermark_defaults_to_disabled(): void
    {
        $response = $this->getJson('/api/settings/photos', $this->authHeader());

        $response->assertOk();
        $response->assertJson(['watermark_enabled' => false]);
    }

    public function test_watermark_reflects_saved_system_setting(): void
    {
        app(SystemSettings::class)->save('media', ['photo_watermark_enabled' => true]);

        $response = $this->getJson('/api/settings/photos', $this->authHeader());

        $response->assertOk();
        $response->assertJson(['watermark_enabled' => true]);
    }
}
