<?php

namespace Tests\Feature;

use App\Models\Operator;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserDestroyTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::create([
            'name' => 'Admin Test',
            'first_name' => 'Admin',
            'apellido_paterno' => 'Test',
            'email' => 'admin-destroy-test-'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'role' => 'admin',
            'is_active' => true,
            'can_login' => true,
        ]);

        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']));

        return $admin;
    }

    private function targetUser(): User
    {
        return User::create([
            'name' => 'Target Test',
            'first_name' => 'Target',
            'apellido_paterno' => 'Test',
            'email' => 'target-destroy-test-'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'role' => 'operator',
            'is_active' => true,
            'can_login' => true,
        ]);
    }

    public function test_destroy_hard_deletes_user_without_history(): void
    {
        $admin = $this->admin();
        $target = $this->targetUser();

        $response = $this->actingAs($admin)->delete(route('users.destroy', $target));

        $response->assertRedirect(route('users.index'));
        $this->assertDatabaseMissing('users', ['id' => $target->id]);
    }

    public function test_destroy_deactivates_instead_of_deleting_when_operator_linked(): void
    {
        $admin = $this->admin();
        $target = $this->targetUser();

        $operator = Operator::create(['code' => 'OPX'.uniqid(), 'name' => 'Operador Vinculado']);
        $target->update(['operator_id' => $operator->id]);

        $response = $this->actingAs($admin)->delete(route('users.destroy', $target));

        $response->assertRedirect(route('users.index'));
        $this->assertDatabaseHas('users', ['id' => $target->id, 'is_active' => false, 'can_login' => false]);
    }

    public function test_destroy_deactivates_instead_of_deleting_when_referenced_in_activity_log(): void
    {
        $admin = $this->admin();
        $target = $this->targetUser();

        DB::table('activity_log')->insert([
            'log_name' => 'usuarios',
            'description' => 'updated',
            'causer_type' => User::class,
            'causer_id' => $target->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->delete(route('users.destroy', $target));

        $response->assertRedirect(route('users.index'));
        $this->assertDatabaseHas('users', ['id' => $target->id, 'is_active' => false]);
    }

    public function test_destroy_deactivates_instead_of_deleting_when_referenced_in_audit_logs(): void
    {
        $admin = $this->admin();
        $target = $this->targetUser();

        DB::table('audit_logs')->insert([
            'user_id' => $target->id,
            'action' => 'update',
            'entity_type' => 'Pet',
            'entity_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->delete(route('users.destroy', $target));

        $response->assertRedirect(route('users.index'));
        $this->assertDatabaseHas('users', ['id' => $target->id, 'is_active' => false]);
    }

    public function test_cannot_delete_own_account(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->delete(route('users.destroy', $admin));

        $response->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }
}
