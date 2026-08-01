<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Client;
use App\Models\Document;
use App\Models\DocumentSeries;
use App\Models\JournalEntry;
use App\Models\PaymentMethod;
use App\Models\Pet;
use App\Models\Quote;
use App\Models\Service;
use App\Models\SpaBooking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BL-076 fase web: aceptar presupuesto con anticipo y "Liquidar Saldo" ahora generan
 * también Document+JournalEntry (antes solo escribían CashLedger/BankLedger sin recibo real).
 */
class QuoteAdvancePaymentAccountingTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Quote Advance Test',
            'first_name' => 'Quote',
            'apellido_paterno' => 'Test',
            'email' => 'quote-advance-test-'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'role' => 'admin',
            'is_active' => true,
            'can_login' => true,
        ]);
    }

    private function fallbackAccount(): Account
    {
        return Account::create(['code' => '4900', 'name' => 'Otros ingresos', 'type' => 'ingreso', 'allows_entries' => true]);
    }

    private function setupAccounting(): PaymentMethod
    {
        $this->fallbackAccount();
        $cashAccount = Account::create(['code' => '1100', 'name' => 'Caja', 'type' => 'activo', 'allows_entries' => true]);
        $paymentMethod = PaymentMethod::create(['code' => 'EFE', 'name' => 'Efectivo', 'type' => 'cash', 'account_id' => $cashAccount->id, 'is_active' => true]);
        DocumentSeries::create(['document_type' => 'recibo', 'name' => 'Recibos', 'prefix' => 'REC-', 'next_number' => 1, 'padding' => 4, 'is_active' => true]);

        return $paymentMethod;
    }

    private function bookingWithQuote(float $price = 1000): array
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Luka']);
        $service = Service::create(['code' => 'VET-'.uniqid(), 'name' => 'Cirugía', 'type' => 'extra', 'price' => $price, 'duration_minutes' => 60]);

        $booking = SpaBooking::create([
            'pet_id' => $pet->id,
            'scheduled_at' => now()->addDay(),
            'status' => 'scheduled',
            'total_estimated_price' => 0,
        ]);

        $quote = Quote::create(['spa_booking_id' => $booking->id, 'status' => 'draft', 'total_amount' => $price]);
        $quote->items()->create(['service_id' => $service->id, 'quantity' => 1, 'price_override' => $price]);

        return [$booking, $quote->fresh(['items'])];
    }

    public function test_accepting_a_quote_with_advance_and_valid_payment_method_generates_a_document(): void
    {
        $paymentMethod = $this->setupAccounting();
        [$booking, $quote] = $this->bookingWithQuote(price: 1000);

        $response = $this->actingAs($this->admin())->post(route('agenda.quotes.accept', [$booking, $quote]), [
            'advance_amount' => 300,
            'advance_payment_method_code' => $paymentMethod->code,
        ]);

        $response->assertRedirect();
        $quote->refresh();
        $this->assertSame('accepted', $quote->status);

        $this->assertDatabaseHas('cash_ledgers', ['amount' => 300, 'category' => 'advance']);
        $document = Document::firstOrFail();
        $this->assertSame('emitido', $document->status);
        $this->assertEquals(300.0, $document->total);
        $this->assertNotEmpty($document->line_items_snapshot);

        $entry = JournalEntry::where('document_id', $document->id)->firstOrFail();
        $this->assertTrue($entry->isBalanced());
    }

    public function test_accepting_a_quote_with_advance_but_no_payment_method_rolls_back_everything(): void
    {
        $this->setupAccounting();
        [$booking, $quote] = $this->bookingWithQuote();

        $response = $this->actingAs($this->admin())->post(route('agenda.quotes.accept', [$booking, $quote]), [
            'advance_amount' => 300,
            // sin advance_payment_method_code
        ]);

        $response->assertRedirect();
        $quote->refresh();
        // La transacción completa se revirtió — el quote NO quedó aceptado
        $this->assertSame('draft', $quote->status);
        $this->assertDatabaseCount('cash_ledgers', 0);
        $this->assertDatabaseCount('documents', 0);
        $this->assertSame('scheduled', $booking->fresh()->status);
    }

    public function test_accepting_a_quote_without_any_advance_still_works_without_a_payment_method(): void
    {
        $this->setupAccounting();
        [$booking, $quote] = $this->bookingWithQuote();

        $response = $this->actingAs($this->admin())->post(route('agenda.quotes.accept', [$booking, $quote]), []);

        $response->assertRedirect();
        $quote->refresh();
        $this->assertSame('accepted', $quote->status);
        $this->assertSame('work_order', $booking->fresh()->status);
        $this->assertDatabaseCount('documents', 0); // sin anticipo, sin recibo
    }

    public function test_liquidar_saldo_with_valid_payment_method_generates_a_document(): void
    {
        $paymentMethod = $this->setupAccounting();
        [$booking, $quote] = $this->bookingWithQuote(price: 1000);
        $this->actingAs($this->admin())->post(route('agenda.quotes.accept', [$booking, $quote]), []);
        $quote->refresh();

        $response = $this->actingAs($this->admin())->post(route('agenda.quotes.register-payment', [$booking, $quote]), [
            'amount' => 1000,
            'payment_method_code' => $paymentMethod->code,
            'category' => 'liquidation',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('cash_ledgers', ['amount' => 1000, 'category' => 'liquidation']);
        $document = Document::firstOrFail();
        $this->assertEquals(1000.0, $document->total);
    }

    public function test_liquidar_saldo_requires_a_valid_payment_method_code(): void
    {
        [$booking, $quote] = $this->bookingWithQuote();
        $this->fallbackAccount();
        DocumentSeries::create(['document_type' => 'recibo', 'name' => 'Recibos', 'prefix' => 'REC-', 'next_number' => 1, 'padding' => 4, 'is_active' => true]);
        $this->actingAs($this->admin())->post(route('agenda.quotes.accept', [$booking, $quote]), []);
        $quote->refresh();

        $response = $this->actingAs($this->admin())->post(route('agenda.quotes.register-payment', [$booking, $quote]), [
            'amount' => 1000,
            'payment_method_code' => 'NO-EXISTE',
            'category' => 'liquidation',
        ]);

        $response->assertSessionHasErrors('payment_method_code');
        $this->assertDatabaseCount('cash_ledgers', 0);
    }
}
