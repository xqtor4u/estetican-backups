<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `POST /login` está limitado por `throttle:login` (ver AppServiceProvider::boot()):
 * 5 intentos/min por (credencial + IP), 30/min por IP.
 */
class LoginThrottleTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $name, string $password): User
    {
        return User::create([
            'name' => $name,
            'first_name' => ucfirst($name),
            'apellido_paterno' => 'Test',
            'email' => $name.'@example.com',
            'password' => bcrypt($password),
            'is_active' => true,
            'can_login' => true,
        ]);
    }

    public function test_blocks_the_sixth_failed_attempt_for_the_same_credential(): void
    {
        $this->user('victima', 'clave-correcta');

        for ($i = 1; $i <= 5; $i++) {
            $this->post('/login', ['email' => 'victima', 'password' => 'incorrecta'])
                ->assertStatus(302); // vuelve con errores de credenciales
        }

        $this->post('/login', ['email' => 'victima', 'password' => 'incorrecta'])
            ->assertStatus(429);
    }

    public function test_a_correct_login_still_works_within_the_limit(): void
    {
        $this->user('operador', 'clave-correcta');

        // Un par de fallos, pero por debajo del tope de 5.
        $this->post('/login', ['email' => 'operador', 'password' => 'incorrecta'])->assertStatus(302);
        $this->post('/login', ['email' => 'operador', 'password' => 'incorrecta'])->assertStatus(302);

        $this->post('/login', ['email' => 'operador', 'password' => 'clave-correcta'])
            ->assertRedirect(route('home'));
        $this->assertAuthenticated();
    }

    public function test_the_per_credential_bucket_does_not_punish_a_different_user(): void
    {
        $this->user('uno', 'x');
        $this->user('dos', 'clave-dos');

        // 5 fallos gastan el bucket de 'uno', no el de 'dos'.
        for ($i = 1; $i <= 5; $i++) {
            $this->post('/login', ['email' => 'uno', 'password' => 'mal'])->assertStatus(302);
        }

        $this->post('/login', ['email' => 'dos', 'password' => 'clave-dos'])
            ->assertRedirect(route('home'));
    }
}
