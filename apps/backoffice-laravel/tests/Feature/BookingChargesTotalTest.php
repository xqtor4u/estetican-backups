<?php

namespace Tests\Feature;

use App\Mail\ServiceSummaryMail;
use App\Models\Client;
use App\Models\Item;
use App\Models\Payment;
use App\Models\Pet;
use App\Models\Quote;
use App\Models\Service;
use App\Models\SpaBooking;
use App\Models\SpaBookingItem;
use App\Models\SpaBookingService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

/**
 * A2 (27/09/2026): el total a cobrar / saldo de una cita sale de un solo lugar,
 * `SpaBooking::chargesTotal()`. Antes el Estado de Cuenta sumaba líneas, mientras que la
 * tarjeta Balance, el recibo, el saldo pendiente y la API móvil usaban el presupuesto
 * aceptado o `total_estimated_price` — podían no coincidir entre pantallas.
 */
class BookingChargesTotalTest extends TestCase
{
    use CreatesAdminUser;
    use RefreshDatabase;

    private function admin(): User
    {
        return $this->createAdminUser();
    }

    /** Cita completada: Baño $300 + Corte $200 (cancelado) + Shampoo $80 (artículo), pagado $100. */
    private function booking(): SpaBooking
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Luka']);
        $booking = SpaBooking::create(['pet_id' => $pet->id, 'scheduled_at' => now()->subHour(), 'status' => 'completed', 'total_estimated_price' => 500]);

        $bano = Service::create(['code' => 'CT-'.uniqid(), 'name' => 'Baño', 'type' => 'spa', 'price' => 300, 'duration_minutes' => 60]);
        $corte = Service::create(['code' => 'CT-'.uniqid(), 'name' => 'Corte', 'type' => 'spa', 'price' => 200, 'duration_minutes' => 30]);
        $shampoo = Item::create(['code' => 'IT-'.uniqid(), 'name' => 'Shampoo', 'price' => 80, 'is_active' => true]);
        SpaBookingService::create(['spa_booking_id' => $booking->id, 'service_id' => $bano->id, 'current_price' => 300]);
        SpaBookingService::create(['spa_booking_id' => $booking->id, 'service_id' => $corte->id, 'current_price' => 200, 'cancelled_at' => now()]);
        SpaBookingItem::create(['spa_booking_id' => $booking->id, 'item_id' => $shampoo->id, 'quantity' => 1, 'current_price' => 80]);
        Payment::create([
            'client_id' => $client->id, 'payable_type' => SpaBooking::class, 'payable_id' => $booking->id,
            'amount' => 100, 'payment_method' => 'Efectivo', 'destination' => 'caja', 'category' => 'liquidacion',
        ]);

        return $booking->fresh();
    }

    public function test_charges_total_counts_billable_services_and_items_and_skips_cancelled_lines(): void
    {
        $booking = $this->booking();

        $this->assertSame(380.0, $booking->chargesTotal());
        $this->assertSame(280.0, $booking->unpaidBalance());
    }

    public function test_every_screen_shows_the_same_total_for_the_same_booking(): void
    {
        $booking = $this->booking();
        $admin = $this->admin();

        $show = $this->actingAs($admin)->get(route('agenda.show', $booking))->assertOk()->getContent();
        $invoice = $this->actingAs($admin)->get(route('reports.invoice', $booking))->assertOk()->getContent();
        $api = $this->withHeaders($this->createAdminAuthHeader($admin))->getJson('/api/bookings/'.$booking->id)->assertOk();

        $this->assertStringContainsString('total $380.00', $show, 'tarjeta Balance');
        $this->assertStringContainsString('$280.00', $show, 'saldo en la tarjeta Balance');
        $this->assertStringContainsString('$380.00', $invoice, 'subtotal del recibo');
        $this->assertStringNotContainsString('$500.00', $invoice, 'el recibo no debe usar el total estimado viejo');
        $this->assertStringContainsString('Shampoo', $invoice, 'el recibo lista los artículos');
        $this->assertStringNotContainsString('Corte', $invoice, 'el recibo no lista líneas canceladas');
        $this->assertEquals(380.0, $api->json('total'), 'API móvil');
    }

    public function test_without_lines_it_falls_back_to_the_accepted_quote_then_the_estimate(): void
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Luka']);
        $booking = SpaBooking::create(['pet_id' => $pet->id, 'scheduled_at' => now()->addDay(), 'status' => 'scheduled', 'total_estimated_price' => 450]);

        $this->assertSame(450.0, $booking->fresh()->chargesTotal());

        Quote::create(['spa_booking_id' => $booking->id, 'status' => 'accepted', 'total_amount' => 520]);

        $this->assertSame(520.0, $booking->fresh()->chargesTotal());
    }

    public function test_service_summary_email_lists_charges_and_total_even_without_a_quote(): void
    {
        $booking = $this->booking();

        $html = (new ServiceSummaryMail($booking))->render();

        $this->assertStringContainsString('Baño', $html);
        $this->assertStringContainsString('Shampoo', $html);
        $this->assertStringNotContainsString('Corte', $html, 'una línea cancelada no se cobra');
        $this->assertStringContainsString('Total: $380.00', $html);
    }
}
