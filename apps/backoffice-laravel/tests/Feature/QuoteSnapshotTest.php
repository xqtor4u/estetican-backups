<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Item;
use App\Models\Pet;
use App\Models\Quote;
use App\Models\Service;
use App\Models\SpaBooking;
use App\Models\SpaBookingService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

/**
 * SYNC-105 (portado desde Zeus/ZEUS-037): `QuoteItem`/`SpaBookingService`/`SpaBookingItem` leían
 * nombre y precio en vivo del catálogo cuando no había un override explícito — un presupuesto
 * pendiente o un recibo ya emitido cambiaba de precio/nombre en silencio si el catálogo se
 * editaba mientras tanto.
 */
class QuoteSnapshotTest extends TestCase
{
    use CreatesAdminUser;
    use RefreshDatabase;

    private function admin(): User
    {
        return $this->createAdminUser();
    }

    private function booking(): SpaBooking
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Luka']);

        return SpaBooking::create([
            'pet_id' => $pet->id,
            'scheduled_at' => now()->addDay(),
            'status' => 'scheduled',
            'total_estimated_price' => 0,
        ]);
    }

    public function test_quote_item_freezes_price_and_name_even_without_explicit_price_override(): void
    {
        $booking = $this->booking();
        $service = Service::create(['code' => 'VET-'.uniqid(), 'name' => 'Veterinario', 'type' => 'extra', 'price' => 100, 'duration_minutes' => 60]);

        // Sin 'price' en el payload — antes esto dejaba price_override en null.
        $this->actingAs($this->admin())->post(route('agenda.quotes.store', $booking), [
            'items' => [
                ['service_id' => $service->id, 'quantity' => 1],
            ],
        ]);

        $quoteItem = Quote::firstOrFail()->items->first();
        $this->assertEquals(100.0, (float) $quoteItem->price_override);
        $this->assertSame('Veterinario', $quoteItem->name_snapshot);

        // Cambia el catálogo DESPUÉS de cotizar.
        $service->update(['price' => 999, 'name' => 'Veterinario Renombrado']);
        $quoteItem->refresh();

        $this->assertEquals(100.0, $quoteItem->unitPrice(), 'unitPrice() no debe seguir al precio vivo del catálogo');
        $this->assertSame('Veterinario', $quoteItem->name(), 'name() no debe seguir al nombre vivo del catálogo');
    }

    public function test_reprinting_a_quote_after_catalog_change_keeps_line_items_matching_the_total(): void
    {
        $booking = $this->booking();
        $service = Service::create(['code' => 'VET-'.uniqid(), 'name' => 'Baño', 'type' => 'extra', 'price' => 100, 'duration_minutes' => 60]);

        $this->actingAs($this->admin())->post(route('agenda.quotes.store', $booking), [
            'items' => [['service_id' => $service->id, 'quantity' => 1]],
        ]);
        $quote = Quote::firstOrFail();

        $service->update(['price' => 500]);
        $quote->refresh()->load('items');

        $lineItemsSum = $quote->items->sum(fn ($item) => $item->lineTotal());
        $this->assertEquals((float) $quote->total_amount, $lineItemsSum, 'El PDF del presupuesto quedaría inconsistente: renglones vs. subtotal impreso');
    }

    public function test_accepting_a_quote_after_a_catalog_price_change_uses_the_originally_quoted_price(): void
    {
        $booking = $this->booking();
        $service = Service::create(['code' => 'VET-'.uniqid(), 'name' => 'Corte', 'type' => 'extra', 'price' => 100, 'duration_minutes' => 60]);

        $this->actingAs($this->admin())->post(route('agenda.quotes.store', $booking), [
            'items' => [['service_id' => $service->id, 'quantity' => 1]],
        ]);
        $quote = Quote::firstOrFail();

        // El precio cambia mientras el presupuesto sigue en draft, antes de aceptarse.
        $service->update(['price' => 999]);

        $this->actingAs($this->admin())->post(route('agenda.quotes.accept', [$booking, $quote]), []);

        $booking->refresh();
        $bookingService = $booking->services->first();

        $this->assertEquals(100.0, (float) $bookingService->current_price, 'Debe cobrarse lo cotizado, no el precio vivo al momento de aceptar');
        $this->assertEquals((float) $quote->total_amount, (float) $booking->total_estimated_price, 'El total de la cita debe cuadrar con el del presupuesto aceptado');
    }

    public function test_accepting_a_quote_snapshots_service_and_item_names_onto_booking_lines(): void
    {
        $booking = $this->booking();
        $service = Service::create(['code' => 'VET-'.uniqid(), 'name' => 'Estética', 'type' => 'extra', 'price' => 100, 'duration_minutes' => 60]);
        $item = Item::create(['name' => 'Shampoo', 'price' => 20]);

        $this->actingAs($this->admin())->post(route('agenda.quotes.store', $booking), [
            'items' => [
                ['service_id' => $service->id, 'quantity' => 1],
                ['item_id' => $item->id, 'quantity' => 1],
            ],
        ]);
        $quote = Quote::firstOrFail();

        $this->actingAs($this->admin())->post(route('agenda.quotes.accept', [$booking, $quote]), []);
        $booking->refresh();

        $this->assertSame('Estética', $booking->services->first()->service_name_snapshot);
        $this->assertSame('Shampoo', $booking->items->first()->item_name_snapshot);

        // Renombrar el catálogo después no debe tocar las líneas ya congeladas.
        $service->update(['name' => 'Estética Renombrada']);
        $item->update(['name' => 'Shampoo Renombrado']);
        $booking->refresh();

        $this->assertSame('Estética', $booking->services->first()->service_name_snapshot);
        $this->assertSame('Shampoo', $booking->items->first()->item_name_snapshot);
    }

    public function test_direct_booking_service_line_snapshots_name_on_creation(): void
    {
        $booking = $this->booking();
        $service = Service::create(['code' => 'VET-'.uniqid(), 'name' => 'Corte', 'type' => 'extra', 'price' => 100, 'duration_minutes' => 60]);

        $line = SpaBookingService::create([
            'spa_booking_id' => $booking->id,
            'service_id' => $service->id,
            'current_price' => 100,
        ]);

        $this->assertSame('Corte', $line->service_name_snapshot);

        $service->update(['name' => 'Corte Renombrado']);
        $line->refresh();

        $this->assertSame('Corte', $line->service_name_snapshot, 'La línea ya creada no debe seguir al nombre vivo del catálogo');
    }

    /**
     * `_billing_summary.blade.php` se muestra para toda cita `completed` cada vez que se
     * reabre su ficha — no es una pantalla de "antes de cobrar", es la vista permanente de una
     * cita ya terminada. Renombrar el servicio después no debe cambiar lo que muestra
     * "Estado de Cuenta y Liquidación" al consultarla más adelante.
     */
    public function test_completed_booking_billing_summary_shows_the_frozen_service_name(): void
    {
        $booking = $this->booking();
        $service = Service::create(['code' => 'VET-'.uniqid(), 'name' => 'Corte de Pelo', 'type' => 'extra', 'price' => 100, 'duration_minutes' => 60]);

        $line = SpaBookingService::create([
            'spa_booking_id' => $booking->id,
            'service_id' => $service->id,
            'current_price' => 100,
        ]);
        $booking->update(['status' => 'completed']);

        $service->update(['name' => 'Corte de Pelo Renombrado']);

        $response = $this->actingAs($this->admin())->get(route('agenda.show', $booking));

        $response->assertOk();
        $response->assertSee('Corte de Pelo', false);
        $response->assertDontSee('Corte de Pelo Renombrado', false);
    }

    /** Mismo hueco que la web, para la API que consume la app móvil. */
    public function test_mobile_booking_detail_returns_the_frozen_service_name(): void
    {
        $booking = $this->booking();
        $service = Service::create(['code' => 'VET-'.uniqid(), 'name' => 'Uñas', 'type' => 'extra', 'price' => 100, 'duration_minutes' => 60]);

        SpaBookingService::create([
            'spa_booking_id' => $booking->id,
            'service_id' => $service->id,
            'current_price' => 100,
        ]);

        $service->update(['name' => 'Uñas Renombrado']);

        $response = $this->getJson('/api/bookings/'.$booking->id, $this->createAdminAuthHeader());

        $response->assertOk();
        $response->assertJsonFragment(['name' => 'Uñas']);
        $response->assertJsonMissing(['name' => 'Uñas Renombrado']);
    }

    /**
     * Hallazgo real de correr la batería beta en Zeus (`ZEUS037-4`, 11/09/2026): la tarjeta
     * "Ejecución Profesional" de `_work_order.blade.php` (cita `work_order`, antes de finalizar)
     * leía `$item->service->name`/`$bookingItem->item->name` EN VIVO — el precio ya estaba bien
     * congelado (`current_price`), pero el nombre no, justo en la pantalla que el operador ve
     * mientras trabaja la cita. El resto de vistas (recibo, estado de cuenta, móvil, API) ya
     * leían el snapshot correctamente; a esta se le había pasado por alto.
     */
    public function test_work_order_card_shows_the_frozen_service_and_item_name(): void
    {
        $booking = $this->booking();
        $service = Service::create(['code' => 'VET-'.uniqid(), 'name' => 'Corte Profesional', 'type' => 'extra', 'price' => 100, 'duration_minutes' => 60]);
        $item = Item::create(['name' => 'Shampoo Medicado', 'price' => 30]);

        SpaBookingService::create([
            'spa_booking_id' => $booking->id,
            'service_id' => $service->id,
            'current_price' => 100,
        ]);
        $booking->items()->create([
            'item_id' => $item->id,
            'item_name_snapshot' => $item->name,
            'quantity' => 1,
            'current_price' => 30,
        ]);
        $booking->update(['status' => 'work_order']);

        $service->update(['name' => 'Corte Profesional Renombrado']);
        $item->update(['name' => 'Shampoo Medicado Renombrado']);

        $response = $this->actingAs($this->admin())->get(route('agenda.show', $booking));

        $response->assertOk();
        $response->assertSee('Corte Profesional', false);
        $response->assertDontSee('Corte Profesional Renombrado', false);
        $response->assertSee('Shampoo Medicado', false);
        $response->assertDontSee('Shampoo Medicado Renombrado', false);
    }
}
