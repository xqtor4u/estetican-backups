<?php

namespace Tests\Feature\Agenda;

use App\Models\Branch;
use App\Models\Client;
use App\Models\Operator;
use App\Models\Pet;
use App\Models\Resource;
use App\Models\ResourceAllocation;
use App\Models\Service;
use App\Models\SpaBooking;
use App\Models\SpaBookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

/**
 * Tomas (SYNC-093): AgSpaEdi no mostraba los detalles de la jaula (Entra/Sale) ni el panel de
 * disponibilidad con las barras de tiempo que ya tenía AgSpaCre — solo un `<select>` de jaula
 * y un `<input datetime-local>` para la fecha, sin forma de ver o ajustar la estancia por
 * separado del servicio. Se porta el mismo panel (barra del operador + barra de la estancia,
 * ambas arrastrables donde aplica) a la pantalla de edición.
 */
class AgendaEditDaybarTest extends TestCase
{
    use CreatesAdminUser;
    use RefreshDatabase;

    private function bookingWithCage(): SpaBooking
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Luka']);
        $operator = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Jose', 'first_name' => 'Jose', 'is_active' => true]);
        $service = Service::create(['code' => 'SVC'.uniqid(), 'name' => 'Baño y corte', 'type' => 'grooming', 'price' => 350, 'duration_minutes' => 60, 'is_active' => true]);
        $branch = Branch::create(['code' => 'MTY-JG001', 'name' => 'Sucursal', 'is_active' => true]);
        $cage = Resource::create([
            'branch_id' => $branch->id,
            'resource_type' => 'cage',
            'code' => 'JG-001',
            'name' => 'Jaula Grande',
            'administrative_status' => 'active',
            'operational_status' => 'available',
        ]);

        $booking = SpaBooking::create([
            'pet_id' => $pet->id,
            'operator_id' => $operator->id,
            'scheduled_at' => now()->addDay()->setTime(11, 0),
            'status' => 'scheduled',
            'duration_minutes' => 60,
            'total_estimated_price' => 350,
        ]);
        SpaBookingService::create(['spa_booking_id' => $booking->id, 'service_id' => $service->id, 'current_price' => 350]);

        ResourceAllocation::create([
            'resource_id' => $cage->id,
            'allocation_type' => 'reserved',
            'starts_at' => $booking->scheduled_at,
            'ends_at' => $booking->scheduled_at->copy()->addHours(2),
            'source_type' => $booking->getMorphClass(),
            'source_id' => $booking->id,
        ]);

        return $booking;
    }

    private function editPage(SpaBooking $booking): TestResponse
    {
        return $this->actingAs($this->createAdminUser())
            ->get(route('agenda.edit', $booking));
    }

    public function test_edit_page_shows_the_availability_panel_and_stay_fields(): void
    {
        $booking = $this->bookingWithCage();
        $res = $this->editPage($booking);

        $res->assertOk();
        $res->assertSee('id="availability_panel"', false);
        $res->assertSee('id="resource_starts_at_input"', false);
        $res->assertSee('id="resource_ends_at_input"', false);
        $res->assertSee('Estancia en la jaula', false);
    }

    public function test_edit_page_ships_the_same_draggable_daybar_widgets_as_create(): void
    {
        $booking = $this->bookingWithCage();
        $res = $this->editPage($booking);

        $res->assertOk();
        $res->assertSee('function attachBlockDrag(', false);
        $res->assertSee('function wireOperatorBlock(', false);
        $res->assertSee('function wireStayBlock(bar, sel, busy)', false);
        $res->assertSee('function stayBarRow(w0, w1)', false);
        $res->assertSee('function dayTimeline(', false);
        // SYNC-095 (9ª vuelta): no hay tarjetas de servicio con duración editable por línea, pero
        // el bloque completo sí se puede alargar/acortar arrastrando su borde derecho — cambia la
        // duración TOTAL de la cita (columna `duration_minutes` de la cita, no de un servicio).
        $res->assertSee("edges: 'end'", false);
        $res->assertSee('var currentDurationMinutes', false);
        $res->assertSee('id="duration_minutes_input"', false);
    }

    /**
     * SYNC-095 (9ª vuelta): Tomas, tras confirmar que la jaula ya funciona bien: "la cita se
     * puede mover pero no redimensionar" — pidió poder alargar/acortar la cita arrastrando su
     * borde, y que un choque real con OTRA cita del mismo operador revierta el arrastre (mover o
     * redimensionar) a como estaba, con un aviso temporal, en vez de reubicarse sola.
     */
    public function test_edit_page_operator_block_resizes_from_the_right_edge_and_reverts_on_real_conflicts(): void
    {
        $booking = $this->bookingWithCage();
        $res = $this->editPage($booking);

        $res->assertOk();
        // Ya no reubica sola sobre un choque real (eso hacía `snapToFit`) — revierte y avisa.
        $res->assertDontSee('commitSelectedMinute(snapToFit(', false);
        $res->assertSee('function flashOccupiedMessage()', false);
        $res->assertSee("if (overlapsBusy(st, en - st, busy)) {\n                        flashOccupiedMessage();", false);
        // El borde derecho cambia `currentDurationMinutes` y el hidden que viaja al servidor.
        $res->assertSee("if (mode === 'end') {\n                        currentDurationMinutes = en - st;", false);
        $res->assertSee('durationHiddenInput.value = currentDurationMinutes', false);
    }

    public function test_edit_page_prefills_the_existing_cage_stay_window(): void
    {
        $booking = $this->bookingWithCage();
        $stayEnd = $booking->scheduled_at->copy()->addHours(2);
        $res = $this->editPage($booking);

        $res->assertOk();
        $res->assertSee('value="'.$booking->scheduled_at->format('Y-m-d\TH:i').'"', false);
        $res->assertSee('value="'.$stayEnd->format('Y-m-d\TH:i').'"', false);
        // Ya hay una estancia real guardada — no debe arrancar como "sin tocar", o el primer
        // `syncStay()` la pisaría con el default "fin del servicio + 1h".
        $res->assertSee('var stayStartTouched = true;', false);
    }

    public function test_check_availability_call_excludes_the_booking_being_edited(): void
    {
        $booking = $this->bookingWithCage();
        $res = $this->editPage($booking);

        $res->assertOk();
        $res->assertSee('exclude_booking_id: EXCLUDE_BOOKING_ID', false);
        $res->assertSee('var EXCLUDE_BOOKING_ID = '.$booking->id.';', false);
    }

    /**
     * Tomas, tras `SYNC-094`: "todavía tiene fallos" en la barra de tiempo de edición — sin más
     * detalle. Por lectura de código: el panel de `AgSpaEdi` había heredado tal cual de
     * `AgSpaCre` la lógica "salta al cierre" (`dayJustChanged`), que en creación tiene sentido
     * (la hora inicial es un default genérico que no conoce la agenda de nadie) pero en edición
     * movía sola la hora de una cita YA agendada en cuanto el chequeo de disponibilidad daba "no
     * disponible" por cualquier motivo — no solo el que corrigió `SYNC-094`. Tomas: "deberían
     * estar totalmente sueltos por ser edición". Se elimina el auto-reacomodo por completo en
     * `AgSpaEdi`; el bloque queda en rojo con el aviso, pero nunca se mueve sin que el usuario
     * lo arrastre o pida "Buscar el próximo hueco".
     */
    public function test_edit_page_never_auto_snaps_the_scheduled_time(): void
    {
        $booking = $this->bookingWithCage();
        $res = $this->editPage($booking);

        $res->assertOk();
        $res->assertDontSee('dayJustChanged', false);
        $res->assertDontSee('lastSchedDate', false);
    }

    // Nota: dos vueltas intermedias de `SYNC-095` (2ª: quitar `snapToFit` del commit; 6ª:
    // restaurarlo para bloquear choques reales) quedaron superadas por la 9ª — ver el historial
    // completo en `PENDIENTES_SINCRONIZAR_ESTETICAN.md`. El comportamiento final (revertir +
    // avisar, no reubicar) se prueba en `test_edit_page_operator_block_resizes_from_the_right_edge_and_reverts_on_real_conflicts`.
}
