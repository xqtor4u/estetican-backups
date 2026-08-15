<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Pet;
use App\Models\SpaBooking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

/**
 * La Bitácora mostraba "–" en vez de los cambios reales de cada evento "Modificado".
 * Causa raíz: spatie/laravel-activitylog v5 guarda el diff en la columna
 * `attribute_changes`, separada de `properties` (que queda vacía) — la vista leía
 * `properties`, la columna equivocada. Los datos completos ya se guardaban.
 */
class ActivityLogChangesDisplayTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAdminUser;

    private function admin(): User
    {
        return $this->createAdminUser();
    }

    public function test_updated_event_shows_the_real_field_diff_instead_of_a_dash(): void
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz'.uniqid()]);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Mascota-'.uniqid()]);
        $booking = SpaBooking::create([
            'pet_id' => $pet->id,
            'scheduled_at' => now(),
            'status' => 'scheduled',
            'total_estimated_price' => 100,
        ]);

        $booking->update(['status' => 'completed']);

        $response = $this->actingAs($this->admin())->get(route('activity-log.index'));

        $response->assertOk();
        $response->assertSee('status');
        $response->assertSee('scheduled');
        $response->assertSee('completed');
    }
}
