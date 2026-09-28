<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Operator;
use App\Models\Payment;
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
 * Fricciones del paso Presupuesto → Orden de Trabajo (auditoría QA/UX 14/09/2026 + UX B.2):
 * EST-002 (el operador elegido al agendar se perdía al aceptar), método de pago obligatorio
 * aunque el anticipo sea $0, etiqueta de versión obligatoria sin indicarlo, y EST-008 (la
 * tarjeta "Balance" llamaba "anticipo" a todo lo pagado).
 */
class QuoteAcceptanceFrictionTest extends TestCase
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

        return SpaBooking::create(['pet_id' => $pet->id, 'scheduled_at' => now()->addDay(), 'status' => 'scheduled', 'total_estimated_price' => 350]);
    }

    private function service(string $name, float $price = 350): Service
    {
        return Service::create(['code' => 'QAF-'.uniqid(), 'name' => $name, 'type' => 'spa', 'price' => $price, 'duration_minutes' => 60]);
    }

    public function test_accepting_a_quote_keeps_the_operator_and_timing_assigned_when_booking(): void
    {
        $booking = $this->booking();
        $bano = $this->service('Baño');
        $corte = $this->service('Corte', 200);
        $nuevo = $this->service('Uñas', 80);
        $operator = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Jose', 'first_name' => 'Jose', 'is_active' => true]);
        $helper = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Rita', 'first_name' => 'Rita', 'is_active' => true]);
        SpaBookingService::create(['spa_booking_id' => $booking->id, 'service_id' => $bano->id, 'current_price' => 350, 'operator_id' => $operator->id, 'duration_minutes' => 45]);
        SpaBookingService::create(['spa_booking_id' => $booking->id, 'service_id' => $corte->id, 'current_price' => 200, 'operator_id' => $helper->id, 'scheduled_offset_minutes' => 45, 'duration_minutes' => 30]);

        $quote = Quote::create(['spa_booking_id' => $booking->id, 'status' => 'draft', 'total_amount' => 630]);
        $quote->items()->create(['service_id' => $bano->id, 'quantity' => 1, 'price_override' => 350]);
        $quote->items()->create(['service_id' => $corte->id, 'quantity' => 1, 'price_override' => 200]);
        $quote->items()->create(['service_id' => $nuevo->id, 'quantity' => 1, 'price_override' => 80]);

        $this->actingAs($this->admin())->post(route('agenda.quotes.accept', [$booking, $quote]), [])->assertRedirect();

        $lines = $booking->fresh()->services->keyBy('service_id');
        $this->assertSame($operator->id, $lines[$bano->id]->operator_id);
        $this->assertSame(45, (int) $lines[$bano->id]->duration_minutes);
        $this->assertSame($helper->id, $lines[$corte->id]->operator_id);
        $this->assertSame(45, (int) $lines[$corte->id]->scheduled_offset_minutes);
        // Un servicio que no estaba al agendar queda "por asignar", como antes.
        $this->assertNull($lines[$nuevo->id]->operator_id);
    }

    public function test_accepting_without_advance_does_not_require_a_payment_method(): void
    {
        $booking = $this->booking();
        $quote = Quote::create(['spa_booking_id' => $booking->id, 'status' => 'draft', 'total_amount' => 350]);
        $quote->items()->create(['service_id' => $this->service('Baño')->id, 'quantity' => 1, 'price_override' => 350]);

        $this->actingAs($this->admin())
            ->post(route('agenda.quotes.accept', [$booking, $quote]), ['advance_amount' => 0])
            ->assertSessionHasNoErrors();

        $this->assertSame('accepted', $quote->fresh()->status);
        $this->assertSame('work_order', $booking->fresh()->status);
    }

    public function test_quote_screens_mark_the_version_label_as_required_and_the_method_as_conditional(): void
    {
        $booking = $this->booking();
        $quote = Quote::create(['spa_booking_id' => $booking->id, 'status' => 'draft', 'total_amount' => 350, 'version_label' => 'A']);
        $quote->items()->create(['service_id' => $this->service('Baño')->id, 'quantity' => 1, 'price_override' => 350]);

        $html = $this->actingAs($this->admin())->get(route('agenda.show', $booking))->assertOk()->getContent();

        $this->assertStringContainsString('Etiqueta de versión <span class="text-danger">*</span>', $html);
        $this->assertStringContainsString(':required="needsMethod"', $html);
        $this->assertStringContainsString('Sin anticipo — no hace falta método de pago.', $html);
    }

    public function test_balance_card_calls_the_total_paid_paid_and_shows_the_real_advance_apart(): void
    {
        $booking = $this->booking();
        $booking->update(['status' => 'completed']);
        foreach ([['advance', 100], ['settlement', 250]] as [$category, $amount]) {
            Payment::create([
                'client_id' => $booking->pet->client_id,
                'payable_type' => SpaBooking::class,
                'payable_id' => $booking->id,
                'amount' => $amount,
                'payment_method' => 'Efectivo',
                'destination' => 'caja',
                'category' => $category,
            ]);
        }

        $html = $this->actingAs($this->admin())->get(route('agenda.show', $booking))->assertOk()->getContent();

        $this->assertStringContainsString('pagado $350.00 (anticipo $100.00) · total $350.00', $html);
        $this->assertStringNotContainsString('anticipo $350.00', $html);
    }
}
