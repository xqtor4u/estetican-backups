<?php

namespace Tests\Feature\Api;

use App\Support\SystemSettings\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandingSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_branding_endpoint_is_public_and_returns_defaults(): void
    {
        $response = $this->getJson('/api/settings/branding');

        $response->assertOk();
        $response->assertJson([
            'business_name' => 'EstetiCAN',
            'logo_url' => null,
            'favicon_url' => null,
        ]);
    }

    public function test_branding_endpoint_exposes_the_configured_logo_and_favicon(): void
    {
        app(SystemSettings::class)->save('branding', [
            'brand_business_name' => 'Clínica Demo',
            'brand_logo_web' => 'branding/logo.png',
            'brand_favicon' => 'branding/favi.png',
        ]);

        $response = $this->getJson('/api/settings/branding');

        $response->assertOk();
        $response->assertJson([
            'business_name' => 'Clínica Demo',
            'logo_url' => '/storage/branding/logo.png',
            'favicon_url' => '/storage/branding/favi.png',
        ]);
    }

    public function test_favicon_falls_back_to_the_logo_when_not_set(): void
    {
        app(SystemSettings::class)->save('branding', [
            'brand_logo_web' => 'branding/logo.png',
        ]);

        $this->getJson('/api/settings/branding')
            ->assertOk()
            ->assertJson([
                'logo_url' => '/storage/branding/logo.png',
                'favicon_url' => '/storage/branding/logo.png',
            ]);
    }
}
