<?php

namespace Tests\Feature\GoogleCalendar;

use App\Models\Operator;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class OperatorGoogleCalendarTest extends TestCase
{
    use RefreshDatabase;

    private function userWithPermissions(array $permissions): User
    {
        $user = User::create([
            'name' => 'Operadores Test',
            'first_name' => 'Operadores',
            'apellido_paterno' => 'Test',
            'email' => 'operadores-test-'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'is_active' => true,
            'can_login' => true,
        ]);

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $user->givePermissionTo($permissions);

        return $user;
    }

    public function test_a_user_with_permission_can_update_google_calendar_settings(): void
    {
        $user = $this->userWithPermissions(['editar operadores']);
        $operator = Operator::create(['code' => 'OP-'.uniqid(), 'name' => 'Test Operator']);

        $response = $this->actingAs($user)->put(route('operators.google-calendar.update', $operator), [
            'google_personal_email' => 'operador@example.com',
            'google_calendar_share_enabled' => '1',
        ]);

        $response->assertRedirect(route('operators.edit', $operator));
        $operator->refresh();
        $this->assertSame('operador@example.com', $operator->google_personal_email);
        $this->assertTrue($operator->google_calendar_share_enabled);
    }

    public function test_changing_the_email_resets_the_shared_at_timestamp(): void
    {
        $user = $this->userWithPermissions(['editar operadores']);
        $operator = Operator::create(['code' => 'OP-'.uniqid(), 'name' => 'Test Operator']);
        $operator->forceFill([
            'google_calendar_id' => 'cal-123',
            'google_personal_email' => 'viejo@example.com',
            'google_calendar_share_enabled' => true,
            'google_calendar_shared_at' => now(),
        ])->save();

        $this->actingAs($user)->put(route('operators.google-calendar.update', $operator), [
            'google_personal_email' => 'nuevo@example.com',
            'google_calendar_share_enabled' => '1',
        ]);

        $operator->refresh();
        $this->assertSame('nuevo@example.com', $operator->google_personal_email);
        $this->assertNull($operator->google_calendar_shared_at);
        $this->assertSame('cal-123', $operator->google_calendar_id);
    }

    public function test_a_user_without_the_permission_cannot_update(): void
    {
        $user = $this->userWithPermissions([]);
        $operator = Operator::create(['code' => 'OP-'.uniqid(), 'name' => 'Test Operator']);

        $this->actingAs($user)->put(route('operators.google-calendar.update', $operator), [
            'google_personal_email' => 'operador@example.com',
            'google_calendar_share_enabled' => '1',
        ])->assertForbidden();

        $this->assertNull($operator->fresh()->google_personal_email);
    }
}
