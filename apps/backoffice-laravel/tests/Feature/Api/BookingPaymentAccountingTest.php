<?php

namespace Tests\Feature\Api;

use App\Domain\Accounting\Contracts\AccountingServiceInterface;
use App\Models\Account;
use App\Models\ApiToken;
use App\Models\Client;
use App\Models\Document;
use App\Models\DocumentSeries;
use App\Models\JournalEntry;
use App\Models\Operator;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Pet;
use App\Models\Service;
use App\Models\SpaBooking;
use App\Models\SpaBookingService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

/**
 * BL-076 fase A: Api\PaymentController::store() ahora genera Document/JournalEntry de forma
 * obligatoria (no silenciada) cuando hay payment_method_code, y liga Payment->document_id.
 * Cubre también AccountingService::cancelEntry() con sus dos ramas (corrección vs reembolso).
 */
class BookingPaymentAccountingTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAdminUser;

    private function apiHeaders(): array
    {
        $user = $this->createAdminUser(['email' => 'admin-payment-accounting-'.uniqid().'@example.com']);
        $plainToken = 'test-token-'.uniqid();
        ApiToken::create(['user_id' => $user->id, 'token' => hash('sha256', $plainToken), 'name' => 'test']);

        return ['Authorization' => "Bearer {$plainToken}"];
    }

    private function fallbackAccount(): Account
    {
        return Account::create(['code' => '4900', 'name' => 'Otros ingresos', 'type' => 'ingreso', 'allows_entries' => true]);
    }

    private function cashPaymentMethod(?int $accountId): PaymentMethod
    {
        return PaymentMethod::create(['code' => 'EFE', 'name' => 'Efectivo', 'type' => 'cash', 'account_id' => $accountId, 'is_active' => true]);
    }

    private function recibosSeries(): DocumentSeries
    {
        return DocumentSeries::create(['document_type' => 'recibo', 'name' => 'Recibos', 'prefix' => 'REC-', 'next_number' => 1, 'padding' => 4, 'is_active' => true]);
    }

    private function bookingWithServiceLine(float $price = 500): array
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Luka']);
        $service = Service::create(['code' => 'SVC'.uniqid(), 'name' => 'Baño', 'type' => 'spa', 'price' => $price, 'duration_minutes' => 30, 'is_active' => true]);
        $operator = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Vet Externo', 'first_name' => 'Vet', 'is_active' => true]);

        $booking = SpaBooking::create([
            'pet_id' => $pet->id,
            'scheduled_at' => now(),
            'status' => 'work_order',
            'total_estimated_price' => $price,
        ]);

        SpaBookingService::create([
            'spa_booking_id' => $booking->id,
            'service_id' => $service->id,
            'quantity' => 1,
            'current_price' => $price,
            'operator_id' => $operator->id,
            'is_external' => true,
            'external_cost' => 300,
        ]);

        return [$booking, $service, $operator];
    }

    public function test_payment_with_method_code_generates_document_and_balanced_journal_entry(): void
    {
        $fallback = $this->fallbackAccount();
        $cashAccount = Account::create(['code' => '1100', 'name' => 'Caja', 'type' => 'activo', 'allows_entries' => true]);
        $this->cashPaymentMethod($cashAccount->id);
        $this->recibosSeries();
        [$booking] = $this->bookingWithServiceLine(price: 500);

        $response = $this->withHeaders($this->apiHeaders())->postJson("/api/bookings/{$booking->id}/payments", [
            'amount' => 500,
            'payment_method_code' => 'EFE',
        ]);

        $response->assertOk();

        $payment = Payment::firstOrFail();
        $this->assertNotNull($payment->document_id);

        $document = Document::findOrFail($payment->document_id);
        $this->assertSame('emitido', $document->status);
        $this->assertSame('REC-0001', $document->folio_display);
        $this->assertEquals(500.0, $document->total);

        $snapshot = $document->line_items_snapshot;
        $this->assertCount(1, $snapshot);
        $this->assertSame('Baño', $snapshot[0]['name']);
        $this->assertSame('Vet Externo', $snapshot[0]['operator_name']);
        $this->assertTrue($snapshot[0]['is_external']);
        $this->assertEquals(300.0, $snapshot[0]['external_cost']);
        $this->assertEquals(500.0, $snapshot[0]['price']);

        $entry = JournalEntry::where('document_id', $document->id)->firstOrFail();
        $this->assertTrue($entry->isBalanced());
        $this->assertDatabaseHas('journal_entry_lines', ['journal_entry_id' => $entry->id, 'account_id' => $fallback->id, 'debit' => 500]);
        $this->assertDatabaseHas('journal_entry_lines', ['journal_entry_id' => $entry->id, 'account_id' => $cashAccount->id, 'credit' => 500]);
    }

    public function test_payment_method_without_account_fails_the_whole_request_no_orphan_payment(): void
    {
        $this->cashPaymentMethod(null); // sin cuenta contable asignada
        $this->recibosSeries();
        [$booking] = $this->bookingWithServiceLine();

        $response = $this->withHeaders($this->apiHeaders())->postJson("/api/bookings/{$booking->id}/payments", [
            'amount' => 500,
            'payment_method_code' => 'EFE',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('payments', 0); // transacción completa revertida, no queda Payment huérfano
    }

    public function test_missing_active_recibo_series_fails_the_whole_request(): void
    {
        $cashAccount = Account::create(['code' => '1100', 'name' => 'Caja', 'type' => 'activo', 'allows_entries' => true]);
        $this->cashPaymentMethod($cashAccount->id);
        // sin DocumentSeries activa de tipo recibo
        [$booking] = $this->bookingWithServiceLine();

        $response = $this->withHeaders($this->apiHeaders())->postJson("/api/bookings/{$booking->id}/payments", [
            'amount' => 500,
            'payment_method_code' => 'EFE',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_legacy_payload_without_method_code_still_records_payment_without_document(): void
    {
        [$booking] = $this->bookingWithServiceLine();

        $response = $this->withHeaders($this->apiHeaders())->postJson("/api/bookings/{$booking->id}/payments", [
            'amount' => 500,
            'payment_method' => 'Efectivo',
            'destination' => 'caja',
        ]);

        $response->assertOk();
        $payment = Payment::firstOrFail();
        $this->assertNull($payment->document_id);
        $this->assertDatabaseCount('documents', 0);
    }

    public function test_cancel_document_correction_does_not_touch_money_nor_the_journal_entry(): void
    {
        $this->fallbackAccount();
        $cashAccount = Account::create(['code' => '1100', 'name' => 'Caja', 'type' => 'activo', 'allows_entries' => true]);
        $this->cashPaymentMethod($cashAccount->id);
        $this->recibosSeries();
        [$booking] = $this->bookingWithServiceLine(price: 500);

        $this->withHeaders($this->apiHeaders())->postJson("/api/bookings/{$booking->id}/payments", [
            'amount' => 500,
            'payment_method_code' => 'EFE',
        ]);

        $document = Document::firstOrFail();
        $entry = JournalEntry::firstOrFail();
        $admin = User::first();

        app(AccountingServiceInterface::class)->cancelDocument($document, $admin, Document::CANCELLATION_TYPE_CORRECTION, 'Nombre del cliente mal capturado');

        $document->refresh();
        $entry->refresh();
        $this->assertSame('cancelado', $document->status);
        $this->assertNotNull($document->cancelled_at);
        $this->assertSame($admin->id, $document->cancelled_by_user_id);
        $this->assertSame(Document::CANCELLATION_TYPE_CORRECTION, $document->cancellation_type);
        // El asiento sigue "aplicado" — el dinero contabilizado sigue siendo correcto,
        // solo el papel (recibo) se corrige.
        $this->assertSame('aplicado', $entry->status);
        $this->assertNull($entry->cancelled_at);
        $this->assertDatabaseCount('cash_ledgers', 0);
        $this->assertDatabaseCount('bank_ledgers', 0);
    }

    public function test_cancel_document_refund_reverses_money_and_cancels_the_journal_entry(): void
    {
        $this->fallbackAccount();
        $cashAccount = Account::create(['code' => '1100', 'name' => 'Caja', 'type' => 'activo', 'allows_entries' => true]);
        $this->cashPaymentMethod($cashAccount->id);
        $this->recibosSeries();
        [$booking] = $this->bookingWithServiceLine(price: 500);

        $this->withHeaders($this->apiHeaders())->postJson("/api/bookings/{$booking->id}/payments", [
            'amount' => 500,
            'payment_method_code' => 'EFE',
        ]);

        $document = Document::firstOrFail();
        $entry = JournalEntry::firstOrFail();
        $admin = User::first();

        app(AccountingServiceInterface::class)->cancelDocument($document, $admin, Document::CANCELLATION_TYPE_REFUND, 'Cliente pidió reembolso');

        $entry->refresh();
        $this->assertSame('cancelado', $entry->status);
        $this->assertNotNull($entry->cancelled_at);
        $this->assertDatabaseHas('cash_ledgers', ['amount' => -500, 'category' => 'reembolso_cancelacion']);
        $this->assertDatabaseCount('bank_ledgers', 0);
    }

    public function test_cannot_cancel_an_already_cancelled_document(): void
    {
        $this->fallbackAccount();
        $cashAccount = Account::create(['code' => '1100', 'name' => 'Caja', 'type' => 'activo', 'allows_entries' => true]);
        $this->cashPaymentMethod($cashAccount->id);
        $this->recibosSeries();
        [$booking] = $this->bookingWithServiceLine(price: 500);

        $this->withHeaders($this->apiHeaders())->postJson("/api/bookings/{$booking->id}/payments", [
            'amount' => 500,
            'payment_method_code' => 'EFE',
        ]);

        $document = Document::firstOrFail();
        $admin = User::first();
        $accounting = app(AccountingServiceInterface::class);

        $accounting->cancelDocument($document, $admin, Document::CANCELLATION_TYPE_CORRECTION, 'Primera cancelación');

        $this->expectException(\RuntimeException::class);
        $accounting->cancelDocument($document->fresh(), $admin, Document::CANCELLATION_TYPE_CORRECTION, 'Segundo intento');
    }
}
