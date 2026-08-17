<?php

namespace Tests\Feature\Finances;

use App\Mail\CashCierresMail;
use App\Mail\CashMetodosPagoMail;
use App\Mail\CashPendientesMail;
use App\Mail\CashPorOperadorMail;
use App\Mail\CashResumenMail;
use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

/**
 * Versión web (backoffice) de los mismos 5 reportes de Caja que ya existen en el celular —
 * misma agregación (`CashReportService`), solo verifica que la página HTML renderiza sin
 * "undefined variable" (el bug real que sí pasó una vez con `close.blade.php`/`$totalCobros`,
 * ver `PENDIENTES_SINCRONIZAR_ESTETICAN.md` SYNC-026) y que PDF/email funcionan igual que en
 * la API móvil, reusando el mismo servicio.
 */
class CashReportControllerTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAdminUser;

    protected function setUp(): void
    {
        parent::setUp();
        Account::forceCreate(['id' => 6, 'code' => '1100', 'name' => 'Caja', 'type' => 'activo', 'allows_entries' => true]);
    }

    private function admin(): User
    {
        return $this->createAdminUser();
    }

    public function test_resumen_page_renders(): void
    {
        $this->actingAs($this->admin())->get(route('finances.cash-reports.resumen'))->assertOk();
    }

    public function test_resumen_pdf_downloads(): void
    {
        $response = $this->actingAs($this->admin())->get(route('finances.cash-reports.resumen.pdf'));
        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_resumen_email_sends_a_pdf_attachment(): void
    {
        Mail::fake();
        $response = $this->actingAs($this->admin())->post(route('finances.cash-reports.resumen.email'), ['email' => 'destino@example.com']);
        $response->assertRedirect();
        $response->assertSessionHas('success');
        Mail::assertSent(CashResumenMail::class, fn (CashResumenMail $mail) => $mail->hasTo('destino@example.com'));
    }

    public function test_metodos_pago_page_renders(): void
    {
        $this->actingAs($this->admin())->get(route('finances.cash-reports.metodos-pago'))->assertOk();
    }

    public function test_metodos_pago_pdf_downloads(): void
    {
        $response = $this->actingAs($this->admin())->get(route('finances.cash-reports.metodos-pago.pdf'));
        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_metodos_pago_email_sends_a_pdf_attachment(): void
    {
        Mail::fake();
        $response = $this->actingAs($this->admin())->post(route('finances.cash-reports.metodos-pago.email'), ['email' => 'destino@example.com']);
        $response->assertRedirect();
        Mail::assertSent(CashMetodosPagoMail::class, fn (CashMetodosPagoMail $mail) => $mail->hasTo('destino@example.com'));
    }

    public function test_por_operador_page_renders(): void
    {
        $this->actingAs($this->admin())->get(route('finances.cash-reports.por-operador'))->assertOk();
    }

    public function test_por_operador_pdf_downloads(): void
    {
        $response = $this->actingAs($this->admin())->get(route('finances.cash-reports.por-operador.pdf'));
        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_por_operador_email_sends_a_pdf_attachment(): void
    {
        Mail::fake();
        $response = $this->actingAs($this->admin())->post(route('finances.cash-reports.por-operador.email'), ['email' => 'destino@example.com']);
        $response->assertRedirect();
        Mail::assertSent(CashPorOperadorMail::class, fn (CashPorOperadorMail $mail) => $mail->hasTo('destino@example.com'));
    }

    public function test_pendientes_page_renders(): void
    {
        $this->actingAs($this->admin())->get(route('finances.cash-reports.pendientes'))->assertOk();
    }

    public function test_pendientes_pdf_downloads(): void
    {
        $response = $this->actingAs($this->admin())->get(route('finances.cash-reports.pendientes.pdf'));
        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_pendientes_email_sends_a_pdf_attachment(): void
    {
        Mail::fake();
        $response = $this->actingAs($this->admin())->post(route('finances.cash-reports.pendientes.email'), ['email' => 'destino@example.com']);
        $response->assertRedirect();
        Mail::assertSent(CashPendientesMail::class, fn (CashPendientesMail $mail) => $mail->hasTo('destino@example.com'));
    }

    public function test_cierres_page_renders(): void
    {
        $this->actingAs($this->admin())->get(route('finances.cash-reports.cierres'))->assertOk();
    }

    public function test_cierres_pdf_downloads(): void
    {
        $response = $this->actingAs($this->admin())->get(route('finances.cash-reports.cierres.pdf'));
        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_cierres_email_sends_a_pdf_attachment(): void
    {
        Mail::fake();
        $response = $this->actingAs($this->admin())->post(route('finances.cash-reports.cierres.email'), ['email' => 'destino@example.com']);
        $response->assertRedirect();
        Mail::assertSent(CashCierresMail::class, fn (CashCierresMail $mail) => $mail->hasTo('destino@example.com'));
    }
}
