<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\SystemSettings\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SystemSettingsBrandingLogoTest extends TestCase
{
    use RefreshDatabase;

    private ?User $admin = null;

    private function admin(): User
    {
        if ($this->admin) {
            return $this->admin;
        }

        Role::findOrCreate('admin', 'web');

        $user = User::create([
            'name' => 'Admin Branding',
            'first_name' => 'Admin',
            'apellido_paterno' => 'Branding',
            'email' => 'admin-branding-logo-test@example.com',
            'password' => bcrypt('secret'),
            'role' => 'admin',
        ]);
        $user->assignRole('admin');

        return $this->admin = $user;
    }

    private function updateBranding(array $overrides = [])
    {
        return $this->actingAs($this->admin())->put(route('system-settings.update', 'branding'), array_merge([
            'brand_business_name' => 'Clínica Demo',
            'brand_whatsapp_number' => '',
        ], $overrides));
    }

    public function test_uploaded_logo_is_scaled_within_bounds_and_stored_as_png(): void
    {
        Storage::fake('public');

        $this->updateBranding([
            'brand_logo_web' => UploadedFile::fake()->image('mi-logo.jpg', 2000, 1200),
        ])->assertRedirect();

        $path = app(SystemSettings::class)->all()['brand_logo_web'];

        $this->assertNotNull($path);
        $this->assertStringEndsWith('.png', $path);
        Storage::disk('public')->assertExists($path);

        [$width, $height, $type] = getimagesize(Storage::disk('public')->path($path));
        $this->assertSame(IMAGETYPE_PNG, $type);
        $this->assertLessThanOrEqual(640, $width);
        $this->assertLessThanOrEqual(240, $height);
        // 2000×1200 → escala = min(640/2000, 240/1200) = 0.2 → 400×240 (proporción intacta).
        $this->assertSame(400, $width);
        $this->assertSame(240, $height);
    }

    public function test_uploaded_favicon_is_scaled_to_128(): void
    {
        Storage::fake('public');

        $this->updateBranding([
            'brand_favicon' => UploadedFile::fake()->image('favi.png', 512, 512),
        ])->assertRedirect();

        $path = app(SystemSettings::class)->all()['brand_favicon'];

        $this->assertStringEndsWith('.png', $path);
        [$width, $height, $type] = getimagesize(Storage::disk('public')->path($path));
        $this->assertSame(IMAGETYPE_PNG, $type);
        $this->assertLessThanOrEqual(128, $width);
        $this->assertLessThanOrEqual(128, $height);
    }

    public function test_small_logo_is_not_upscaled(): void
    {
        Storage::fake('public');

        $this->updateBranding([
            'brand_logo_web' => UploadedFile::fake()->image('chico.png', 200, 80),
        ])->assertRedirect();

        $path = app(SystemSettings::class)->all()['brand_logo_web'];
        [$width, $height] = getimagesize(Storage::disk('public')->path($path));

        $this->assertSame(200, $width);
        $this->assertSame(80, $height);
    }

    public function test_saving_branding_without_a_new_file_keeps_the_existing_logo(): void
    {
        Storage::fake('public');

        $this->updateBranding([
            'brand_logo_web' => UploadedFile::fake()->image('logo.png', 800, 300),
        ])->assertRedirect();

        $stored = app(SystemSettings::class)->all()['brand_logo_web'];
        Storage::disk('public')->assertExists($stored);

        // Segundo guardado sin archivo nuevo — el logo no debe cambiar ni borrarse.
        $this->updateBranding(['brand_business_name' => 'Otro Nombre'])->assertRedirect();

        $this->assertSame($stored, app(SystemSettings::class)->all()['brand_logo_web']);
        Storage::disk('public')->assertExists($stored);
    }

    public function test_replacing_the_logo_deletes_the_previous_file(): void
    {
        Storage::fake('public');

        $this->updateBranding([
            'brand_logo_web' => UploadedFile::fake()->image('v1.png', 800, 300),
        ])->assertRedirect();
        $first = app(SystemSettings::class)->all()['brand_logo_web'];

        $this->updateBranding([
            'brand_logo_web' => UploadedFile::fake()->image('v2.png', 800, 300),
        ])->assertRedirect();
        $second = app(SystemSettings::class)->all()['brand_logo_web'];

        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
    }
}
