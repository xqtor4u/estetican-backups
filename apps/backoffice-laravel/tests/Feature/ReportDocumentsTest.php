<?php

namespace Tests\Feature;

use App\Mail\ReportDocumentMail;
use App\Models\Client;
use App\Models\Operator;
use App\Models\Payment;
use App\Models\Pet;
use App\Models\Phone;
use App\Models\Quote;
use App\Models\Service;
use App\Models\SpaBooking;
use App\Models\SpaBookingService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\CreatesAdminUser;
use Tests\Concerns\CreatesRestrictedOperatorUser;
use Tests\TestCase;

/**
 * A1 (27/09/2026): presupuesto, orden de trabajo y recibo en PDF; presupuesto y recibo se
 * envían al cliente por correo (PDF adjunto) o WhatsApp (link firmado que caduca). La orden de
 * trabajo es interna — nunca se envía. De paso, los documentos respetan la agenda propia del
 * operador restringido (antes cualquiera con `ver agenda` abría el de cualquier cita).
 */
class ReportDocumentsTest extends TestCase
{
    use CreatesAdminUser;
    use CreatesRestrictedOperatorUser;
    use RefreshDatabase;

    private function admin(): User
    {
        return $this->createAdminUser();
    }

    /** @return array{SpaBooking, Quote} */
    private function completedBooking(?int $operatorId = null): array
    {
        $client = Client::create(['first_name' => 'Renata', 'apellido_paterno' => 'Vidal', 'email' => 'renata@example.com']);
        Phone::create(['client_id' => $client->id, 'type' => 'mobile', 'number' => '8110000001', 'sort_order' => 0]);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Habibi']);
        $booking = SpaBooking::create(['pet_id' => $pet->id, 'operator_id' => $operatorId, 'scheduled_at' => now()->subHour(), 'status' => 'completed', 'total_estimated_price' => 250]);
        $service = Service::create(['code' => 'RD-'.uniqid(), 'name' => 'Baño perro chico', 'type' => 'spa', 'price' => 250, 'duration_minutes' => 60]);
        SpaBookingService::create(['spa_booking_id' => $booking->id, 'service_id' => $service->id, 'current_price' => 250]);
        $quote = Quote::create(['spa_booking_id' => $booking->id, 'status' => 'draft', 'total_amount' => 250, 'version_label' => 'A']);
        $quote->items()->create(['service_id' => $service->id, 'quantity' => 1, 'price_override' => 250, 'name_snapshot' => 'Baño perro chico']);
        Payment::create(['client_id' => $client->id, 'payable_type' => SpaBooking::class, 'payable_id' => $booking->id, 'amount' => 250, 'payment_method' => 'Efectivo', 'destination' => 'caja', 'category' => 'liquidacion']);

        return [$booking->fresh(), $quote];
    }

    public function test_each_document_downloads_as_a_real_pdf(): void
    {
        [$booking, $quote] = $this->completedBooking();
        $admin = $this->admin();

        foreach (['quote' => $quote->id, 'work-order' => $booking->id, 'invoice' => $booking->id] as $document => $id) {
            $response = $this->actingAs($admin)->get(route('reports.pdf', [$document, $id]));

            $response->assertOk();
            $response->assertHeader('content-type', 'application/pdf');
            $this->assertStringStartsWith('%PDF', $response->getContent(), $document);
            $this->assertLessThan(200_000, strlen($response->getContent()), "$document: el PDF debe usar subconjunto de fuente");
        }
    }

    public function test_email_sends_the_pdf_attached_to_the_given_address(): void
    {
        Mail::fake();
        [$booking] = $this->completedBooking();

        $this->actingAs($this->admin())
            ->post(route('reports.email', ['invoice', $booking->id]), ['email' => 'renata@example.com'])
            ->assertRedirect()
            ->assertSessionHas('success');

        Mail::assertSent(ReportDocumentMail::class, function (ReportDocumentMail $mail) {
            return $mail->hasTo('renata@example.com')
                && $mail->title === 'Recibo de pago'
                && str_starts_with($mail->pdfContent, '%PDF')
                && str_ends_with($mail->filename, '.pdf');
        });
    }

    public function test_whatsapp_opens_a_chat_with_a_signed_link_that_serves_the_pdf_without_login(): void
    {
        [$booking, $quote] = $this->completedBooking();

        $redirect = $this->actingAs($this->admin())->get(route('reports.whatsapp', ['quote', $quote->id]));

        $redirect->assertRedirect();
        $target = $redirect->headers->get('Location');
        $this->assertStringStartsWith('https://api.whatsapp.com/send?phone=528110000001&text=', $target);

        parse_str(parse_url($target, PHP_URL_QUERY), $query);
        $this->assertStringContainsString('el presupuesto de Habibi', $query['text']);
        preg_match('#https?://\S+/documentos/quote/'.$quote->id.'\S+#', $query['text'], $m);
        $this->assertNotEmpty($m, 'el mensaje lleva el link firmado al PDF');

        auth()->logout();
        $public = $this->get($m[0]);
        $public->assertOk();
        $this->assertStringStartsWith('%PDF', $public->getContent());

        $this->get(preg_replace('/signature=[a-f0-9]+/', 'signature=alterada', $m[0]))->assertForbidden();

        $this->travel(8)->days();
        $this->get($m[0])->assertForbidden();
    }

    public function test_the_internal_work_order_can_never_be_sent_to_the_client(): void
    {
        Mail::fake();
        [$booking] = $this->completedBooking();
        $admin = $this->admin();

        $this->actingAs($admin)->post('/reports/work-order/'.$booking->id.'/email', ['email' => 'x@example.com'])->assertNotFound();
        $this->actingAs($admin)->get('/reports/work-order/'.$booking->id.'/whatsapp')->assertNotFound();
        Mail::assertNothingSent();
    }

    public function test_restricted_operator_cannot_open_documents_of_someone_elses_booking(): void
    {
        $mine = Operator::create(['code' => 'OP-RD1', 'name' => 'Mío', 'first_name' => 'Mío', 'is_active' => true]);
        $other = Operator::create(['code' => 'OP-RD2', 'name' => 'Otro', 'first_name' => 'Otro', 'is_active' => true]);
        [$booking, $quote] = $this->completedBooking($other->id);
        $user = $this->createOperatorUser(['ver agenda'], $mine);

        $this->actingAs($user)->get(route('reports.invoice', $booking))->assertNotFound();
        $this->actingAs($user)->get(route('reports.quote', $quote))->assertNotFound();
        $this->actingAs($user)->get(route('reports.pdf', ['invoice', $booking->id]))->assertNotFound();
    }

    public function test_booking_screen_offers_pdf_and_sending_only_where_it_applies(): void
    {
        [$booking, $quote] = $this->completedBooking();

        $html = $this->actingAs($this->admin())->get(route('agenda.show', $booking))->assertOk()->getContent();

        $this->assertStringContainsString(route('reports.pdf', ['work-order', $booking->id]), $html);
        $this->assertStringContainsString(route('reports.whatsapp', ['invoice', $booking->id]), $html);
        $this->assertStringContainsString(route('reports.email', ['quote', $quote->id]), $html);
        $this->assertStringNotContainsString(route('reports.email', ['work-order', $booking->id]), $html);
        $this->assertStringContainsString('value="renata@example.com"', $html);
    }
}
