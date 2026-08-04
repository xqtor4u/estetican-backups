<?php

namespace Tests\Feature\Agenda;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La vista Día de AgUniInd solo traía el switch Hoy/Mañana/Próximas/Todas —
 * a diferencia de Semana/Mes (que sí tienen "« anterior" / "siguiente »"),
 * no había forma de saltar al día anterior/siguiente sin usar el date-picker
 * "Fecha operativa" a mano.
 */
class AgendaDayNavigationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin Test',
            'first_name' => 'Admin',
            'apellido_paterno' => 'Test',
            'email' => 'admin-agenda-day-nav-test-'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'role' => 'admin',
        ]);
    }

    public function test_day_view_shows_previous_and_next_day_navigation_links(): void
    {
        $response = $this->actingAs($this->admin())
            ->get(route('agenda.index', ['status_touched' => 1, 'date_scope' => 'today']));

        $response->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString('Día anterior', $html);
        $this->assertStringContainsString('Día siguiente', $html);

        $prev = now()->subDay()->format('Y-m-d');
        $next = now()->addDay()->format('Y-m-d');

        $this->assertStringContainsString('date_scope=custom', $html);
        $this->assertStringContainsString(urlencode($prev), $html);
        $this->assertStringContainsString(urlencode($next), $html);
    }

    public function test_navigating_to_the_next_day_lands_on_that_exact_date(): void
    {
        $target = now()->addDay()->format('Y-m-d');

        $response = $this->actingAs($this->admin())
            ->get(route('agenda.index', ['status_touched' => 1, 'date_scope' => 'custom', 'date' => $target]));

        $response->assertOk();
        $response->assertSee('Fecha elegida');
    }

    public function test_day_navigation_is_hidden_for_the_proximas_and_todas_scopes(): void
    {
        foreach (['all', 'full'] as $scope) {
            $response = $this->actingAs($this->admin())
                ->get(route('agenda.index', ['status_touched' => 1, 'date_scope' => $scope]));

            $response->assertOk();
            $response->assertDontSee('Día anterior');
            $response->assertDontSee('Día siguiente');
        }
    }
}
