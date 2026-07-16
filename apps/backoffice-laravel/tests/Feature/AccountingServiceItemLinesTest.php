<?php

namespace Tests\Feature;

use App\Domain\Accounting\Contracts\AccountingServiceInterface;
use App\Models\Account;
use App\Models\Client;
use App\Models\DocumentSeries;
use App\Models\Item;
use App\Models\PaymentMethod;
use App\Models\Pet;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\SpaBooking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cubre el fix crítico encontrado al diseñar "Grupos": AccountingService::buildDebitLines()/
 * buildDebitLinesFromBooking() asumían que toda línea de cotización tenía `service` — con
 * líneas de artículo (item_id) tronaban con fatal error o prorrateaban el ingreso en silencio.
 */
class AccountingServiceItemLinesTest extends TestCase
{
    use RefreshDatabase;

    private function fallbackAccount(): Account
    {
        return Account::create(['code' => '4900', 'name' => 'Otros ingresos', 'type' => 'ingreso', 'allows_entries' => true]);
    }

    private function issuer(): User
    {
        return User::create([
            'name' => 'Cajero Test',
            'first_name' => 'Cajero',
            'apellido_paterno' => 'Test',
            'email' => 'cajero-test-'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'is_active' => true,
            'can_login' => true,
        ]);
    }

    private function quoteWithItemLine(?int $itemAccountId = null): Quote
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Luka']);
        $booking = SpaBooking::create(['pet_id' => $pet->id, 'scheduled_at' => now()->addDay(), 'status' => 'scheduled', 'total_estimated_price' => 0]);
        $item = Item::create(['name' => 'Venda', 'price' => 10, 'account_id' => $itemAccountId]);

        $quote = Quote::create(['spa_booking_id' => $booking->id, 'status' => 'accepted', 'total_amount' => 50]);
        QuoteItem::create(['quote_id' => $quote->id, 'item_id' => $item->id, 'quantity' => 5, 'price_override' => 10]);

        return $quote->fresh();
    }

    public function test_creating_a_payment_entry_for_a_quote_with_item_lines_does_not_crash(): void
    {
        $this->fallbackAccount();
        $cashAccount = Account::create(['code' => '1100', 'name' => 'Caja', 'type' => 'activo', 'allows_entries' => true]);
        $paymentMethod = PaymentMethod::create(['code' => 'EFE', 'name' => 'Efectivo', 'type' => 'cash', 'account_id' => $cashAccount->id, 'is_active' => true]);
        $series = DocumentSeries::create(['document_type' => 'recibo', 'name' => 'Recibos', 'prefix' => 'REC-', 'next_number' => 1, 'padding' => 4, 'is_active' => true]);

        $quote = $this->quoteWithItemLine();

        $entry = app(AccountingServiceInterface::class)->createPaymentEntry(
            $quote, $paymentMethod, $series->id, 50.0, $this->issuer()
        );

        $this->assertNotNull($entry->id);
        $this->assertDatabaseHas('journal_entry_lines', ['journal_entry_id' => $entry->id, 'credit' => 50]);
    }

    public function test_booking_payment_journal_entry_uses_the_item_account_when_present(): void
    {
        $this->fallbackAccount();
        $itemAccount = Account::create(['code' => '4400', 'name' => 'Ingresos — Accesorios', 'type' => 'ingreso', 'allows_entries' => true]);
        $cashAccount = Account::create(['code' => '1100', 'name' => 'Caja', 'type' => 'activo', 'allows_entries' => true]);
        $paymentMethod = PaymentMethod::create(['code' => 'EFE', 'name' => 'Efectivo', 'type' => 'cash', 'account_id' => $cashAccount->id, 'is_active' => true]);
        $series = DocumentSeries::create(['document_type' => 'recibo', 'name' => 'Recibos', 'prefix' => 'REC-', 'next_number' => 1, 'padding' => 4, 'is_active' => true]);

        $quote = $this->quoteWithItemLine($itemAccount->id);

        $entry = app(AccountingServiceInterface::class)->createPaymentEntry(
            $quote, $paymentMethod, $series->id, 50.0, $this->issuer()
        );

        $this->assertDatabaseHas('journal_entry_lines', [
            'journal_entry_id' => $entry->id,
            'account_id' => $itemAccount->id,
            'debit' => 50,
        ]);
    }

    public function test_booking_payment_journal_entry_falls_back_to_4900_when_item_has_no_account(): void
    {
        $fallback = $this->fallbackAccount();
        $cashAccount = Account::create(['code' => '1100', 'name' => 'Caja', 'type' => 'activo', 'allows_entries' => true]);
        $paymentMethod = PaymentMethod::create(['code' => 'EFE', 'name' => 'Efectivo', 'type' => 'cash', 'account_id' => $cashAccount->id, 'is_active' => true]);
        $series = DocumentSeries::create(['document_type' => 'recibo', 'name' => 'Recibos', 'prefix' => 'REC-', 'next_number' => 1, 'padding' => 4, 'is_active' => true]);

        $quote = $this->quoteWithItemLine(null);

        $entry = app(AccountingServiceInterface::class)->createPaymentEntry(
            $quote, $paymentMethod, $series->id, 50.0, $this->issuer()
        );

        $this->assertDatabaseHas('journal_entry_lines', [
            'journal_entry_id' => $entry->id,
            'account_id' => $fallback->id,
            'debit' => 50,
        ]);
    }

    public function test_booking_freeze_with_item_lines_does_not_crash_accounting(): void
    {
        $this->fallbackAccount();
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Luka']);
        $booking = SpaBooking::create(['pet_id' => $pet->id, 'scheduled_at' => now()->addDay(), 'status' => 'work_order', 'total_estimated_price' => 0]);
        $item = Item::create(['name' => 'Venda', 'price' => 10]);
        $booking->items()->create(['item_id' => $item->id, 'quantity' => 5, 'current_price' => 50]);

        $service = new \ReflectionClass(\App\Domain\Accounting\Services\AccountingService::class);
        $method = $service->getMethod('buildDebitLinesFromBooking');
        $method->setAccessible(true);

        $entry = \App\Models\JournalEntry::create([
            'entry_date' => now()->toDateString(),
            'description' => 'Test',
            'status' => 'aplicado',
        ]);

        $method->invoke(app(AccountingServiceInterface::class), $entry, $booking, 50.0);

        $this->assertDatabaseHas('journal_entry_lines', ['journal_entry_id' => $entry->id, 'debit' => 50]);
    }
}
