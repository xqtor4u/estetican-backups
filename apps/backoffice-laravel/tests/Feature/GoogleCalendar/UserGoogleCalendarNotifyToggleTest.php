<?php

namespace Tests\Feature\GoogleCalendar;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * El switch "Enviarme un resumen de cambios por correo (lo manda EstetiCAN)" en la
 * pantalla de edición de usuario (USEEDI), sección Google Calendar. Solo controla el
 * correo resumen de EstetiCAN (`notifyWatchers`), no los avisos nativos de Google.
 */
class UserGoogleCalendarNotifyToggleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // La pantalla de edición renderiza la matriz CRUD y el switch de disponibilidad
        // propia, que referencian estos permisos por nombre.
        foreach ([
            'ver disponibilidad_propia', 'crear disponibilidad_propia', 'eliminar disponibilidad_propia',
        ] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
    }

    private function admin(): User
    {
        $admin = User::create([
            'name' => 'Admin Test',
            'first_name' => 'Admin',
            'apellido_paterno' => 'Test',
            'email' => 'admin-gcal-notify-'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'role' => 'admin',
            'is_active' => true,
            'can_login' => true,
        ]);

        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']));

        return $admin;
    }

    private function targetUser(array $overrides = []): User
    {
        return User::create(array_merge([
            'name' => 'Operador Test',
            'first_name' => 'Operador',
            'apellido_paterno' => 'Test',
            'email' => 'operador-gcal-notify-'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'role' => 'operator',
            'is_active' => true,
            'can_login' => true,
        ], $overrides));
    }

    private function baseFields(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'role' => 'operator',
            'is_active' => true,
            'can_login' => true,
            'is_operator' => false,
        ];
    }

    public function test_edit_page_shows_the_notify_switch(): void
    {
        $admin = $this->admin();
        $user = $this->targetUser();

        $this->actingAs($admin)
            ->get(route('users.edit', $user))
            ->assertOk()
            ->assertSee('Enviarme un resumen de cambios por correo (lo manda EstetiCAN)')
            // Deja claro que la casilla NO apaga los avisos nativos de Google.
            ->assertSee('esta casilla solo controla el correo de EstetiCAN');
    }

    public function test_checking_the_switch_persists_it(): void
    {
        $admin = $this->admin();
        $user = $this->targetUser();

        $this->actingAs($admin)
            ->put(route('users.update', $user), array_merge($this->baseFields($user), [
                'google_calendar_notify_email' => '1',
            ]))
            ->assertRedirect();

        $this->assertTrue($user->refresh()->google_calendar_notify_email);
    }

    public function test_unchecking_the_switch_persists_it(): void
    {
        $admin = $this->admin();
        $user = $this->targetUser(['google_calendar_notify_email' => true]);

        $this->actingAs($admin)
            ->put(route('users.update', $user), array_merge($this->baseFields($user), [
                'google_calendar_notify_email' => '0',
            ]))
            ->assertRedirect();

        $this->assertFalse($user->refresh()->google_calendar_notify_email);
    }
}
