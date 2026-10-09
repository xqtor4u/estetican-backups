<?php

namespace Tests\Feature\Series;

use App\Domain\Planning\Series\BookingSeriesService;
use App\Domain\Planning\Series\RecurrenceRule;
use App\Mail\SeriesDailyReportMail;
use App\Models\Client;
use App\Models\Operator;
use App\Models\Payment;
use App\Models\Pet;
use App\Models\Service;
use App\Models\SpaBooking;
use App\Models\SpaBookingSeries;
use App\Models\SpaBookingSeriesEvent;
use App\Models\User;
use App\Support\Notifications\EmailNotificationTypes;
use Database\Seeders\BaseRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

/**
 * ZEUS-047 Fase 3 — vida de una serie: una cita sin fijar es virtual (se fija, se descarta o se
 * borra sola al terminar su día), pausa por rango, cancelar de aquí en adelante, extender, la
 * pantalla Agenda → Series recurrentes y el reporte diario por correo.
 */
class SeriesLifecycleTest extends TestCase
{
    use CreatesAdminUser;
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-07 08:00:00')); // miércoles
        $this->admin = $this->createAdminUser();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Cada 7 días desde el jueves 08/10 10:00 hasta el 30/11: 8 citas (08/10 … 26/11). */
    private function series(string $until = '2026-11-30'): SpaBookingSeries
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Luka']);
        $operator = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Jose', 'first_name' => 'Jose', 'is_active' => true]);
        $service = Service::create(['code' => 'BC'.uniqid(), 'name' => 'Baño', 'type' => 'spa', 'price' => 250, 'duration_minutes' => 60, 'open_to_all_operators' => true]);

        $this->actingAs($this->admin);

        return app(BookingSeriesService::class)->create([
            'pet_id' => $pet->id,
            'operator_id' => $operator->id,
            'lines' => [['service_id' => $service->id, 'service_name' => $service->name, 'operator_id' => $operator->id, 'duration_minutes' => 60, 'price' => 250]],
        ], RecurrenceRule::everyNDays(7), Carbon::parse('2026-10-08 10:00'), Carbon::parse($until));
    }

    private function bookingOn(SpaBookingSeries $series, string $date): SpaBooking
    {
        return $series->bookings()->whereDate('scheduled_at', $date)->firstOrFail();
    }

    public function test_pin_records_who_fixed_it(): void
    {
        $series = $this->series();
        $booking = $this->bookingOn($series, '2026-10-08');

        $this->post(route('agenda.series.pin', $booking))->assertSessionHas('success');

        $booking->refresh();
        $this->assertSame($this->admin->id, $booking->series_confirmed_by_user_id);
        $this->assertDatabaseHas('spa_booking_series_events', [
            'series_id' => $series->id, 'spa_booking_id' => $booking->id, 'type' => SpaBookingSeriesEvent::PINNED, 'user_id' => $this->admin->id,
        ]);
    }

    public function test_discard_deletes_the_virtual_booking_and_keeps_a_trace(): void
    {
        $series = $this->series();
        $booking = $this->bookingOn($series, '2026-10-15');

        $this->from(route('agenda.show', $booking))
            ->post(route('agenda.series.discard', $booking))
            ->assertRedirect(route('agenda.index'))
            ->assertSessionHas('success');

        $this->assertModelMissing($booking);
        $this->assertSame(7, $series->bookings()->count());
        $this->assertDatabaseHas('spa_booking_series_events', [
            'series_id' => $series->id, 'spa_booking_id' => $booking->id, 'type' => SpaBookingSeriesEvent::DISCARDED, 'user_id' => $this->admin->id,
        ]);
    }

    public function test_a_pinned_booking_or_one_with_payment_cannot_be_discarded(): void
    {
        $series = $this->series();
        $pinned = $this->bookingOn($series, '2026-10-08');
        $this->post(route('agenda.series.pin', $pinned));

        $this->post(route('agenda.series.discard', $pinned))->assertSessionHas('error');
        $this->assertModelExists($pinned);

        $paid = $this->bookingOn($series, '2026-10-15');
        Payment::create(['client_id' => $paid->pet->client_id, 'payable_type' => SpaBooking::class, 'payable_id' => $paid->id, 'amount' => 100, 'payment_method' => 'efectivo', 'destination' => 'caja']);

        $this->post(route('agenda.series.discard', $paid))->assertSessionHas('error');
        $this->assertModelExists($paid);
    }

    public function test_daily_close_deletes_unpinned_bookings_of_the_day_and_keeps_pinned_ones(): void
    {
        $series = $this->series();
        $first = $this->bookingOn($series, '2026-10-08');
        $second = $this->bookingOn($series, '2026-10-15');
        $this->post(route('agenda.series.pin', $second));

        Carbon::setTestNow(Carbon::parse('2026-10-15 23:50:00'));
        $this->artisan('series:cierre-diario', ['--sin-correo' => true])->assertSuccessful();

        $this->assertModelMissing($first);
        $this->assertModelExists($second);
        $this->assertSame(1, SpaBookingSeriesEvent::where('type', SpaBookingSeriesEvent::EXPIRED)->count());
        $this->assertSame(7, $series->bookings()->count(), 'las futuras sin fijar no se tocan');
    }

    public function test_daily_close_mails_the_report_only_to_opted_in_users(): void
    {
        Mail::fake();
        $series = $this->series();
        $this->bookingOn($series, '2026-10-08');

        $this->admin->update(['email_notifications' => ['series_daily' => true]]);
        $silent = User::factory()->create(['email_notifications' => ['series_daily' => false]]);

        Carbon::setTestNow(Carbon::parse('2026-10-08 23:50:00'));
        $this->artisan('series:cierre-diario')->assertSuccessful();

        Mail::assertSent(SeriesDailyReportMail::class, fn ($mail) => $mail->hasTo($this->admin->email) && $mail->report['expired']->count() === 1);
        Mail::assertNotSent(SeriesDailyReportMail::class, fn ($mail) => $mail->hasTo($silent->email));
    }

    public function test_pause_clears_the_range_and_the_series_resumes_after_it(): void
    {
        $series = $this->series();
        $pinnedInRange = $this->bookingOn($series, '2026-10-22');
        $this->post(route('agenda.series.pin', $pinnedInRange));

        $this->post(route('agenda.series.pause', $series), ['paused_from' => '2026-10-20', 'paused_until' => '2026-11-04'])
            ->assertRedirect(route('agenda.series.show', $series))
            ->assertSessionHas('success');

        $this->assertSame('cancelled', $pinnedInRange->fresh()->status, 'la fijada es cita real: se cancela, no se borra');
        $this->assertFalse($series->bookings()->whereDate('scheduled_at', '2026-10-29')->exists());
        $this->assertTrue($series->bookings()->whereDate('scheduled_at', '2026-11-05')->exists());

        Carbon::setTestNow(Carbon::parse('2026-10-25 09:00'));
        $this->assertTrue(SpaBookingSeries::tab('pausa')->whereKey($series->id)->exists());
        Carbon::setTestNow(Carbon::parse('2026-11-05 09:00'));
        $this->assertTrue(SpaBookingSeries::tab('activas')->whereKey($series->id)->exists());
    }

    public function test_cancel_from_a_date_leaves_earlier_bookings(): void
    {
        $series = $this->series();

        $this->post(route('agenda.series.cancel', $series), ['cancel_from' => '2026-10-20'])->assertSessionHas('success');

        $series->refresh();
        $this->assertSame(SpaBookingSeries::STATUS_CANCELLED, $series->status);
        $this->assertSame($this->admin->id, $series->cancelled_by_user_id);
        $this->assertSame(2, $series->bookings()->count());
        $this->assertTrue(SpaBookingSeries::tab('terminadas')->whereKey($series->id)->exists());
    }

    public function test_extend_keeps_the_cadence_and_adds_only_new_dates(): void
    {
        $series = $this->series();

        $this->post(route('agenda.series.extend', $series), ['ends_on' => '2026-12-20'])->assertSessionHas('success');

        $series->refresh();
        $this->assertSame('2026-12-20', $series->ends_on->toDateString());
        $this->assertSame(
            ['2026-12-03 10:00', '2026-12-10 10:00', '2026-12-17 10:00'],
            $series->bookings()->where('scheduled_at', '>', '2026-11-30')->get()->map(fn ($b) => $b->scheduled_at->format('Y-m-d H:i'))->all(),
        );
        $this->assertSame(11, $series->bookings()->count());

        $this->post(route('agenda.series.extend', $series), ['ends_on' => '2026-12-01'])->assertSessionHasErrors('ends_on');
    }

    public function test_extend_stops_at_the_occurrence_cap(): void
    {
        $series = $this->series();

        $this->post(route('agenda.series.extend', $series), ['ends_on' => '2028-06-30'])->assertSessionHas('success');

        $series->refresh();
        $this->assertSame('2027-11-25', $series->ends_on->toDateString(), 'cita 60 = 08/10/2026 + 59 semanas');
        $this->assertSame(60, $series->bookings()->count());

        $this->post(route('agenda.series.extend', $series), ['ends_on' => '2028-07-01'])->assertSessionHas('error');
        $this->assertSame(60, $series->bookings()->count());
    }

    public function test_series_screen_lists_tabs_and_series(): void
    {
        $series = $this->series('2026-10-30');

        $this->get(route('agenda.series.index'))->assertOk()->assertSee('Luka')->assertSee('Cada 7 días');
        $this->get(route('agenda.series.index', ['tab' => 'terminan']))->assertOk()->assertSee('Luka');
        $this->get(route('agenda.series.show', $series))->assertOk()->assertSee('Extender vigencia')->assertSee('Descartar');
        $this->get(route('agenda.index'))->assertOk()->assertSee('serie recurrente termina este mes');
    }

    public function test_series_screen_requires_its_permission_but_discard_only_needs_edit_agenda(): void
    {
        $series = $this->series();
        $booking = $this->bookingOn($series, '2026-10-15');

        (new BaseRolesSeeder)->run();
        $role = Role::create(['name' => 'recepcion-sin-series', 'guard_name' => 'web']);
        $role->givePermissionTo(['ver agenda', 'editar agenda', 'agenda.ver_todas']);
        $user = User::factory()->create();
        $user->assignRole($role);

        $this->actingAs($user)->get(route('agenda.series.index'))->assertForbidden();
        $this->actingAs($user)->post(route('agenda.series.cancel', $series), ['cancel_from' => '2026-10-20'])->assertForbidden();
        $this->actingAs($user)->post(route('agenda.series.discard', $booking))->assertSessionHas('success');
        $this->assertModelMissing($booking);
    }

    public function test_mobile_discard_endpoint(): void
    {
        $series = $this->series();
        $booking = $this->bookingOn($series, '2026-10-15');
        $headers = $this->createAdminAuthHeader($this->admin);

        $this->withHeaders($headers)->postJson('/api/bookings/'.$booking->id.'/descartar')->assertOk();
        $this->assertModelMissing($booking);

        $pinned = $this->bookingOn($series, '2026-10-22');
        $this->withHeaders($headers)->postJson('/api/bookings/'.$pinned->id.'/fijar')->assertOk();
        $this->withHeaders($headers)->postJson('/api/bookings/'.$pinned->id.'/descartar')->assertStatus(422);
    }

    public function test_user_form_saves_email_notifications(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin)->get(route('users.edit', $user))->assertOk()->assertSee('Reporte diario de citas recurrentes');

        $this->put(route('users.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'role' => 'operator',
            'is_active' => true,
            'can_login' => true,
            'is_operator' => false,
            'email_notifications' => ['series_daily' => '1'],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame(['series_daily' => true], $user->refresh()->email_notifications);
        $this->assertTrue(EmailNotificationTypes::recipients(EmailNotificationTypes::SERIES_DAILY)->contains('id', $user->id));
    }
}
