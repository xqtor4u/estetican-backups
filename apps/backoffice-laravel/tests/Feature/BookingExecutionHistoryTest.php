<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ExecutedService;
use App\Models\Operator;
use App\Models\Pet;
use App\Models\Service;
use App\Models\SpaBooking;
use App\Models\SpaBookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

/**
 * B4 (27/09/2026): al completarse una cita se congela lo que se ejecutó en executed_services /
 * executed_service_items — historial inmutable aunque la cita se edite después.
 */
class BookingExecutionHistoryTest extends TestCase
{
    use CreatesAdminUser;
    use RefreshDatabase;

    /** Cita en orden de trabajo: Baño $300 (Jose, 45 min) + Corte $200 cancelado. */
    private function workOrder(): array
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Luka']);
        $jose = Operator::create(['code' => 'OP-B4', 'name' => 'Jose', 'first_name' => 'Jose', 'is_active' => true]);
        $booking = SpaBooking::create(['pet_id' => $pet->id, 'operator_id' => $jose->id, 'scheduled_at' => now(), 'status' => 'work_order', 'total_estimated_price' => 300, 'notes' => 'Llegó con nudos']);
        $bano = Service::create(['code' => 'B4-1', 'name' => 'Baño', 'type' => 'spa', 'price' => 300, 'duration_minutes' => 60]);
        $corte = Service::create(['code' => 'B4-2', 'name' => 'Corte', 'type' => 'spa', 'price' => 200, 'duration_minutes' => 30]);
        SpaBookingService::create(['spa_booking_id' => $booking->id, 'service_id' => $bano->id, 'current_price' => 300, 'operator_id' => $jose->id, 'duration_minutes' => 45]);
        SpaBookingService::create(['spa_booking_id' => $booking->id, 'service_id' => $corte->id, 'current_price' => 200, 'cancelled_at' => now()]);

        return [$booking->fresh(), $bano, $jose];
    }

    public function test_completing_a_booking_freezes_what_was_executed(): void
    {
        [$booking, $bano, $jose] = $this->workOrder();

        $booking->update(['status' => 'completed']);

        $executed = ExecutedService::with('items')->where('spa_booking_id', $booking->id)->sole();
        $this->assertEquals(300.0, (float) $executed->final_price);
        $this->assertSame('Baño', $executed->service_summary);
        $this->assertSame('Llegó con nudos', $executed->notes);
        $this->assertCount(1, $executed->items, 'la línea cancelada no se ejecutó');
        $item = $executed->items->first();
        $this->assertSame([$bano->id, 'Baño', 300.0, 45, $jose->id], [$item->service_id, $item->service_name_snapshot, (float) $item->charged_price, (int) $item->duration_minutes_snapshot, $item->operator_id]);
    }

    public function test_editing_the_booking_after_completion_does_not_change_the_history(): void
    {
        [$booking, $bano] = $this->workOrder();
        $booking->update(['status' => 'completed']);

        // Lo que hace la edición de una cita cerrada: borra y recrea sus líneas; el catálogo cambia.
        $booking->services()->delete();
        SpaBookingService::create(['spa_booking_id' => $booking->id, 'service_id' => $bano->id, 'current_price' => 999]);
        $bano->update(['name' => 'Baño renombrado']);
        $booking->update(['notes' => 'nota editada']);

        $executed = ExecutedService::with('items')->where('spa_booking_id', $booking->id)->sole();
        $this->assertEquals(300.0, (float) $executed->final_price);
        $this->assertSame('Baño', $executed->items->first()->service_name_snapshot);
        $this->assertSame('Llegó con nudos', $executed->notes);
    }

    public function test_reopening_and_completing_again_replaces_the_record(): void
    {
        [$booking] = $this->workOrder();
        $booking->update(['status' => 'completed']);
        $booking->update(['status' => 'work_order']);
        $booking->services()->whereNull('cancelled_at')->update(['current_price' => 350]);

        $booking->fresh()->update(['status' => 'completed']);

        $this->assertSame(1, ExecutedService::where('spa_booking_id', $booking->id)->count());
        $this->assertEquals(350.0, (float) ExecutedService::where('spa_booking_id', $booking->id)->value('final_price'));
    }

    public function test_mobile_payment_that_closes_the_booking_records_the_execution(): void
    {
        [$booking] = $this->workOrder();

        $this->withHeaders($this->createAdminAuthHeader())
            ->postJson('/api/bookings/'.$booking->id.'/payments', [
                'amount' => 300,
                'payment_method' => 'Efectivo',
                'destination' => 'caja',
                'mark_completed' => true,
            ])
            ->assertSuccessful();

        $this->assertSame('completed', $booking->fresh()->status);
        $this->assertSame(1, ExecutedService::where('spa_booking_id', $booking->id)->count());
    }

    public function test_backfill_command_records_old_completed_bookings_once(): void
    {
        [$booking] = $this->workOrder();
        SpaBooking::withoutEvents(fn () => $booking->update(['status' => 'completed'])); // como antes de B4
        $this->assertSame(0, ExecutedService::count());

        $this->artisan('ejecuciones:registrar-historico')->assertSuccessful();
        $this->artisan('ejecuciones:registrar-historico')->assertSuccessful();

        $this->assertSame(1, ExecutedService::where('spa_booking_id', $booking->id)->count());
    }
}
