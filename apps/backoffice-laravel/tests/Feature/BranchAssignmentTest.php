<?php

namespace Tests\Feature;

use App\Domain\Accounting\Services\CashReportService;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Operator;
use App\Models\Payment;
use App\Models\Pet;
use App\Models\Phone;
use App\Models\Service;
use App\Models\SpaBooking;
use App\Models\User;
use App\Models\WhatsAppTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

/**
 * B1 (27/09/2026): citas y pagos guardan su sucursal. Se asigna al crearlos (un solo lugar,
 * BranchResolver vía eventos `creating`) y nunca se adivina: sin información queda null.
 */
class BranchAssignmentTest extends TestCase
{
    use CreatesAdminUser;
    use RefreshDatabase;

    private function pet(): Pet
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);

        return Pet::create(['client_id' => $client->id, 'name' => 'Luka']);
    }

    private function newBooking(?int $operatorId = null): SpaBooking
    {
        return SpaBooking::create(['pet_id' => $this->pet()->id, 'operator_id' => $operatorId, 'scheduled_at' => now()->addDay(), 'status' => 'scheduled', 'total_estimated_price' => 100]);
    }

    public function test_a_new_booking_takes_the_branch_of_whoever_creates_it(): void
    {
        Branch::create(['code' => 'B1-A', 'name' => 'Centro', 'is_active' => true]);
        $norte = Branch::create(['code' => 'B1-B', 'name' => 'Norte', 'is_active' => true]);
        $this->actingAs($this->createAdminUser(['branch_id' => $norte->id]));

        $this->assertSame($norte->id, $this->newBooking()->branch_id);
    }

    public function test_without_a_user_branch_it_uses_the_operator_single_branch(): void
    {
        Branch::create(['code' => 'B1-A', 'name' => 'Centro', 'is_active' => true]);
        $norte = Branch::create(['code' => 'B1-B', 'name' => 'Norte', 'is_active' => true]);
        $operator = Operator::create(['code' => 'OP-B1', 'name' => 'Jose', 'first_name' => 'Jose', 'is_active' => true]);
        $operator->branches()->attach($norte->id);
        $this->actingAs($this->createAdminUser());

        $this->assertSame($norte->id, $this->newBooking($operator->id)->branch_id);
    }

    public function test_with_a_single_active_branch_everything_lands_there(): void
    {
        $unica = Branch::create(['code' => 'B1-U', 'name' => 'Matriz', 'is_active' => true]);
        $this->actingAs($this->createAdminUser());

        $this->assertSame($unica->id, $this->newBooking()->branch_id);
    }

    public function test_it_never_guesses_between_several_branches(): void
    {
        Branch::create(['code' => 'B1-A', 'name' => 'Centro', 'is_active' => true]);
        Branch::create(['code' => 'B1-B', 'name' => 'Norte', 'is_active' => true]);
        $this->actingAs($this->createAdminUser());

        $this->assertNull($this->newBooking()->branch_id);
    }

    public function test_a_payment_takes_the_branch_of_its_booking(): void
    {
        Branch::create(['code' => 'B1-A', 'name' => 'Centro', 'is_active' => true]);
        $norte = Branch::create(['code' => 'B1-B', 'name' => 'Norte', 'is_active' => true]);
        $booking = SpaBooking::create(['pet_id' => $this->pet()->id, 'branch_id' => $norte->id, 'scheduled_at' => now(), 'status' => 'completed', 'total_estimated_price' => 100]);
        $this->actingAs($this->createAdminUser());

        $payment = Payment::create(['client_id' => $booking->pet->client_id, 'payable_type' => SpaBooking::class, 'payable_id' => $booking->id, 'amount' => 100, 'payment_method' => 'Efectivo', 'destination' => 'caja', 'category' => 'liquidacion']);

        $this->assertSame($norte->id, $payment->branch_id);
    }

    public function test_mobile_booking_creation_records_the_branch(): void
    {
        $unica = Branch::create(['code' => 'B1-U', 'name' => 'Matriz', 'is_active' => true]);
        $pet = $this->pet();
        $service = Service::create(['code' => 'B1-S', 'name' => 'Baño', 'type' => 'spa', 'price' => 100, 'duration_minutes' => 60, 'open_to_all_operators' => true]);
        $operator = Operator::create(['code' => 'OP-B1M', 'name' => 'Jose', 'first_name' => 'Jose', 'is_active' => true]);
        $admin = $this->createAdminUser();

        $this->withHeaders($this->createAdminAuthHeader($admin))->postJson('/api/bookings', [
            'pet_id' => $pet->id,
            'operator_id' => $operator->id,
            'scheduled_at' => now()->addDay()->setTime(11, 0)->format('Y-m-d H:i:s'),
            'services' => [['id' => $service->id, 'operator_id' => $operator->id]],
            'override_availability' => true,
        ])->assertCreated();

        $this->assertSame($unica->id, SpaBooking::latest('id')->first()->branch_id);
    }

    public function test_cash_report_shows_a_branch_user_only_the_payments_of_their_branch(): void
    {
        $centro = Branch::create(['code' => 'B1-A', 'name' => 'Centro', 'is_active' => true]);
        $norte = Branch::create(['code' => 'B1-B', 'name' => 'Norte', 'is_active' => true]);
        foreach ([[$centro, 100], [$norte, 250]] as [$branch, $amount]) {
            $booking = SpaBooking::create(['pet_id' => $this->pet()->id, 'branch_id' => $branch->id, 'scheduled_at' => now(), 'status' => 'completed', 'total_estimated_price' => $amount]);
            Payment::create(['client_id' => $booking->pet->client_id, 'payable_type' => SpaBooking::class, 'payable_id' => $booking->id, 'amount' => $amount, 'payment_method' => 'Efectivo', 'destination' => 'caja', 'category' => 'liquidacion']);
        }
        $user = User::create([
            'name' => 'Cajera Norte', 'first_name' => 'Cajera', 'apellido_paterno' => 'Norte',
            'email' => 'cajera-'.uniqid().'@example.com', 'password' => bcrypt('secret'),
            'role' => 'operador', 'is_active' => true, 'can_login' => true, 'branch_id' => $norte->id,
        ]);
        $this->actingAs($user);

        $data = app(CashReportService::class)->buildResumenData(Request::create('/', 'GET', ['date_from' => now()->subDay()->toDateString()]));

        $this->assertSame(250.0, $data['totalCobrado']);
    }

    public function test_sucursal_in_a_cita_template_is_the_booking_branch(): void
    {
        $norte = Branch::create(['code' => 'B1-B', 'name' => 'Norte', 'is_active' => true]);
        Branch::create(['code' => 'B1-A', 'name' => 'Centro', 'is_active' => true]);
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);
        Phone::create(['client_id' => $client->id, 'type' => 'mobile', 'number' => '8110000001', 'sort_order' => 0]);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Luka']);
        $booking = SpaBooking::create(['pet_id' => $pet->id, 'branch_id' => $norte->id, 'scheduled_at' => now()->addDay(), 'status' => 'scheduled', 'total_estimated_price' => 100]);
        $template = WhatsAppTemplate::create(['name' => 'Suc', 'body' => 'Te esperamos en {sucursal}', 'context' => 'cita', 'is_active' => true]);

        $this->actingAs($this->createAdminUser())
            ->getJson(route('clients.whatsapp.link', $client).'?phone=8110000001&template_id='.$template->id.'&booking_id='.$booking->id)
            ->assertOk()
            ->assertJson(['message' => 'Te esperamos en Norte']);
    }
}
