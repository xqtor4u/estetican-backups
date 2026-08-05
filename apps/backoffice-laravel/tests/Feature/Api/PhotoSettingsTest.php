<?php

namespace Tests\Feature\Api;

use App\Models\ApiToken;
use App\Models\User;
use App\Support\SystemSettings\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

class PhotoSettingsTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAdminUser;

    private function authHeader(): array
    {
        return $this->createAdminAuthHeader();
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
