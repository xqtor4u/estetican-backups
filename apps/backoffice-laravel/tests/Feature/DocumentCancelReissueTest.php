<?php

namespace Tests\Feature;

use App\Domain\Accounting\Contracts\AccountingServiceInterface;
use App\Models\Account;
use App\Models\Client;
use App\Models\Document;
use App\Models\DocumentSeries;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Pet;
use App\Models\Service;
use App\Models\SpaBooking;
use App\Models\SpaBookingService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DocumentCancelReissueTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate('admin', 'web');

        $user = User::create([
            'name' => 'Admin Test',
            'first_name' => 'Admin',
            'apellido_paterno' => 'Test',
            'email' => 'admin-doc-cancel-test-'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'role' => 'admin',
            'is_active' => true,
            'can_login' => true,
        ]);
        $user->assignRole('admin');

        return $user;
    }

    private function operator(): User
    {
        return User::create([
            'name' => 'Groomer Test',
            'first_name' => 'Groomer',
            'apellido_paterno' => 'Test',
            'email' => 'groomer-doc-cancel-test-'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'is_active' => true,
            'can_login' => true,
        ]);
    }

    private function documentWithBooking(float $price = 500): array
    {
        $this->fallbackAccount();
        $cashAccount = Account::create(['code' => '1100', 'name' => 'Caja', 'type' => 'activo', 'allows_entries' => true]);
        $paymentMethod = PaymentMethod::create(['code' => 'EFE', 'name' => 'Efectivo', 'type' => 'cash', 'account_id' => $cashAccount->id, 'is_active' => true]);
        DocumentSeries::create(['document_type' => 'recibo', 'name' => 'Recibos', 'prefix' => 'REC-', 'next_number' => 1, 'padding' => 4, 'is_active' => true]);

        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Luka']);
        $service = Service::create(['code' => 'SVC'.uniqid(), 'name' => 'Baño', 'type' => 'spa', 'price' => $price, 'duration_minutes' => 30, 'is_active' => true]);

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
        ]);

        $this->actingAs($this->admin());
        $payment = Payment::create([
            'client_id' => $client->id,
            'payable_type' => SpaBooking::class,
            'payable_id' => $booking->id,
            'amount' => $price,
            'payment_method' => 'Efectivo',
            'destination' => 'caja',
            'category' => 'liquidacion',
        ]);
        $document = app(AccountingServiceInterface::class)->recordBookingPayment($booking, $payment, $paymentMethod, $price);

        return [$document, $booking];
    }

    private function fallbackAccount(): Account
    {
        return Account::create(['code' => '4900', 'name' => 'Otros ingresos', 'type' => 'ingreso', 'allows_entries' => true]);
    }

    public function test_admin_can_cancel_an_issued_document_as_correction(): void
    {
        [$document] = $this->documentWithBooking();

        $response = $this->actingAs($this->admin())->post(route('finances.documents.cancel', $document), [
            'cancellation_type' => 'correction',
            'cancellation_reason' => 'Nombre del cliente mal capturado',
        ]);

        $response->assertRedirect();
        $document->refresh();
        $this->assertSame('cancelado', $document->status);
        $this->assertSame('correction', $document->cancellation_type);
    }

    public function test_non_admin_cannot_cancel_a_document(): void
    {
        [$document] = $this->documentWithBooking();

        $response = $this->actingAs($this->operator())->post(route('finances.documents.cancel', $document), [
            'cancellation_type' => 'correction',
            'cancellation_reason' => 'Intento no autorizado',
        ]);

        $response->assertForbidden();
        $this->assertSame('emitido', $document->fresh()->status);
    }

    public function test_reissuing_a_correction_cancelled_document_creates_a_new_one_and_repoints_entry_and_payment(): void
    {
        [$document, $booking] = $this->documentWithBooking(price: 500);
        $originalEntry = JournalEntry::where('document_id', $document->id)->firstOrFail();
        $originalPayment = Payment::where('document_id', $document->id)->firstOrFail();

        $admin = $this->admin();
        $this->actingAs($admin)->post(route('finances.documents.cancel', $document), [
            'cancellation_type' => 'correction',
            'cancellation_reason' => 'Servicio mal descrito',
        ]);

        $response = $this->actingAs($admin)->post(route('finances.documents.reissue', $document));
        $response->assertRedirect();

        $newDocument = Document::where('supersedes_document_id', $document->id)->firstOrFail();
        $this->assertSame('emitido', $newDocument->status);
        $this->assertSame('REC-0002', $newDocument->folio_display);
        $this->assertEquals(500.0, $newDocument->total);
        $this->assertNotEmpty($newDocument->line_items_snapshot);

        $originalEntry->refresh();
        $originalPayment->refresh();
        $this->assertSame($newDocument->id, $originalEntry->document_id);
        $this->assertSame($newDocument->id, $originalPayment->document_id);
        // El asiento nunca se canceló (era una corrección) — sigue aplicado, solo cambió a qué documento apunta.
        $this->assertSame('aplicado', $originalEntry->status);
    }

    public function test_cannot_reissue_a_refund_cancelled_document(): void
    {
        [$document] = $this->documentWithBooking();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('finances.documents.cancel', $document), [
            'cancellation_type' => 'refund',
            'cancellation_reason' => 'Cliente pidió su dinero de vuelta',
        ]);

        $response = $this->actingAs($admin)->post(route('finances.documents.reissue', $document));

        $response->assertRedirect();
        $this->assertDatabaseCount('documents', 1); // no se creó un segundo documento
    }

    public function test_cannot_reissue_a_document_twice(): void
    {
        [$document] = $this->documentWithBooking();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('finances.documents.cancel', $document), [
            'cancellation_type' => 'correction',
            'cancellation_reason' => 'Corrección menor',
        ]);
        $this->actingAs($admin)->post(route('finances.documents.reissue', $document));

        // Segundo intento de reemitir el mismo documento ya reemitido
        $response = $this->actingAs($admin)->post(route('finances.documents.reissue', $document));

        $response->assertRedirect();
        $this->assertDatabaseCount('documents', 2); // no se creó un tercero
    }

    public function test_billing_summary_renders_the_documents_section_for_a_user_with_permission(): void
    {
        [$document, $booking] = $this->documentWithBooking();
        $booking->update(['status' => 'completed']);

        $admin = $this->admin();
        $admin->givePermissionTo(Permission::findOrCreate('asientos.aprobar', 'web'));

        $response = $this->actingAs($admin)->get(route('agenda.show', $booking));

        $response->assertOk();
        $response->assertSee('Recibos generados');
        $response->assertSee($document->folio_display);
    }

    public function test_billing_summary_hides_the_documents_section_without_permission(): void
    {
        [$document, $booking] = $this->documentWithBooking();
        $booking->update(['status' => 'completed']);

        $response = $this->actingAs($this->operator())->get(route('agenda.show', $booking));

        $response->assertOk();
        $response->assertDontSee('Recibos generados');
    }
}
