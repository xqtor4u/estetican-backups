<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\DocumentSeries;
use App\Models\Pet;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Service;
use App\Models\SpaBooking;
use App\Models\SpaBookingService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Antes, presupuesto/orden de trabajo/recibo mostraban 3 números distintos e
 * inconsistentes para la misma cita: el presupuesto usaba el ID del Quote (no
 * el del booking), la orden de trabajo usaba el ID crudo del booking ("Sesión
 * #34"), el recibo armaba un "R-000034" a mano — ninguno era el order_folio
 * real (ya generado con serie numerada + candado, y ya usado en la app móvil).
 * Ahora los tres usan order_folio; el primer documento que se imprima lo asigna
 * si el booking todavía no tiene uno, y los demás lo heredan.
 */
class ReportOrderFolioTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin Folio',
            'first_name' => 'Admin',
            'apellido_paterno' => 'Folio',
            'email' => 'admin-folio-test-'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'role' => 'admin',
            'is_active' => true,
            'can_login' => true,
        ]);
    }

    private function ordenSpaSeries(): DocumentSeries
    {
        return DocumentSeries::create([
            'document_type' => 'orden_spa',
            'name' => 'Órdenes de servicio SPA',
            'prefix' => 'OT-SPA-',
            'next_number' => 1,
            'padding' => 6,
            'is_active' => true,
        ]);
    }

    private function bookingWithQuote(): array
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Luka']);
        $service = Service::create(['code' => 'SVC'.uniqid(), 'name' => 'Baño y corte', 'type' => 'grooming', 'price' => 350, 'duration_minutes' => 60, 'is_active' => true]);

        $booking = SpaBooking::create([
            'pet_id' => $pet->id,
            'scheduled_at' => now(),
            'status' => 'work_order',
            'total_estimated_price' => 350,
        ]);

        SpaBookingService::create([
            'spa_booking_id' => $booking->id,
            'service_id' => $service->id,
            'current_price' => 350,
        ]);

        $quote = Quote::create([
            'spa_booking_id' => $booking->id,
            'version_label' => 'V1',
            'status' => 'accepted',
            'total_amount' => 350,
        ]);
        QuoteItem::create(['quote_id' => $quote->id, 'service_id' => $service->id, 'quantity' => 1]);

        return [$booking, $quote];
    }

    public function test_work_order_report_shows_the_real_order_folio_not_the_raw_booking_id(): void
    {
        $this->ordenSpaSeries();
        [$booking] = $this->bookingWithQuote();

        $response = $this->actingAs($this->admin())->get(route('reports.work-order', $booking));

        $response->assertOk();
        $response->assertSee('OT-SPA-000001', false);
        $response->assertDontSee('Sesión #'.$booking->id);
    }

    public function test_invoice_report_shows_the_real_order_folio_not_a_fabricated_r_number(): void
    {
        $this->ordenSpaSeries();
        [$booking] = $this->bookingWithQuote();

        $response = $this->actingAs($this->admin())->get(route('reports.invoice', $booking));

        $response->assertOk();
        $response->assertSee('OT-SPA-000001', false);
        $response->assertDontSee('Folio #R-'.str_pad((string) $booking->id, 6, '0', STR_PAD_LEFT), false);
    }

    public function test_quote_report_shows_the_booking_order_folio_not_the_quote_id(): void
    {
        $this->ordenSpaSeries();
        [$booking, $quote] = $this->bookingWithQuote();

        $response = $this->actingAs($this->admin())->get(route('reports.quote', $quote));

        $response->assertOk();
        $response->assertSee('OT-SPA-000001', false);
    }

    public function test_a_quote_printed_before_the_booking_has_a_folio_assigns_one_on_the_fly(): void
    {
        $this->ordenSpaSeries();
        [$booking, $quote] = $this->bookingWithQuote();
        $this->assertNull($booking->fresh()->order_folio);

        $this->actingAs($this->admin())->get(route('reports.quote', $quote))->assertOk();

        $this->assertSame('OT-SPA-000001', $booking->fresh()->order_folio);
    }

    public function test_the_three_documents_for_the_same_booking_share_the_exact_same_folio(): void
    {
        $this->ordenSpaSeries();
        [$booking, $quote] = $this->bookingWithQuote();
        $admin = $this->admin();

        $quoteHtml = $this->actingAs($admin)->get(route('reports.quote', $quote))->getContent();
        $workOrderHtml = $this->actingAs($admin)->get(route('reports.work-order', $booking))->getContent();
        $invoiceHtml = $this->actingAs($admin)->get(route('reports.invoice', $booking))->getContent();

        $this->assertStringContainsString('OT-SPA-000001', $quoteHtml);
        $this->assertStringContainsString('OT-SPA-000001', $workOrderHtml);
        $this->assertStringContainsString('OT-SPA-000001', $invoiceHtml);
    }

    public function test_without_an_active_series_the_reports_fall_back_gracefully_instead_of_erroring(): void
    {
        // Sin crear ninguna DocumentSeries — assignOrderFolio() debe devolver null sin tronar.
        [$booking] = $this->bookingWithQuote();

        $response = $this->actingAs($this->admin())->get(route('reports.work-order', $booking));

        $response->assertOk();
        $response->assertSee('Sesión #'.$booking->id);
    }
}
