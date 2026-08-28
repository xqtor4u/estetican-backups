<?php

namespace Tests\Feature\GoogleCalendar;

use App\Domain\GoogleCalendar\Contracts\GoogleCalendarSyncServiceInterface;
use App\Mail\GoogleCalendarUpdatedMail;
use App\Models\Client;
use App\Models\Operator;
use App\Models\Pet;
use App\Models\SpaBooking;
use App\Models\User;
use App\Support\SystemSettings\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SincronizarGoogleCalendarCommandTest extends TestCase
{
    use RefreshDatabase;

    private function enableSync(): void
    {
        app(SystemSettings::class)->saveFields('calendario_google', [
            'google_calendar_sync_enabled' => true,
        ]);
    }

    private function sharedOperator(): Operator
    {
        return Operator::create([
            'code' => 'OP-'.uniqid(),
            'name' => 'Operador Test',
            'google_personal_email' => 'operador@example.com',
            'google_calendar_share_enabled' => true,
        ]);
    }

    private function bookingFor(Operator $operator, array $overrides = []): SpaBooking
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Luka']);

        return SpaBooking::create(array_merge([
            'pet_id' => $pet->id,
            'operator_id' => $operator->id,
            'scheduled_at' => now()->addHours(2),
            'status' => 'scheduled',
            'total_estimated_price' => 250,
        ], $overrides));
    }

    public function test_syncs_eligible_booking(): void
    {
        $this->enableSync();
        $operator = $this->sharedOperator();
        $booking = $this->bookingFor($operator);

        $this->mock(GoogleCalendarSyncServiceInterface::class, function ($mock) use ($operator, $booking) {
            $mock->shouldReceive('ensureCalendarForOperator')->once()->with(\Mockery::on(fn ($o) => $o->is($operator)))->andReturn('cal-123');
            $mock->shouldReceive('shareCalendarWithEmail')->once()->with('cal-123', 'operador@example.com')->andReturn(true);
            $mock->shouldReceive('upsertBookingEvent')->once()->with(\Mockery::on(fn ($b) => $b->is($booking)));
        });

        $this->artisan('calendario:sincronizar-google')->assertSuccessful();

        $this->assertNotNull($operator->fresh()->google_calendar_shared_at);
    }

    public function test_deletes_event_for_cancelled_booking_with_event_id(): void
    {
        $this->enableSync();
        $operator = $this->sharedOperator();
        $operator->forceFill(['google_calendar_id' => 'cal-123', 'google_calendar_shared_at' => now()])->save();
        $booking = $this->bookingFor($operator, ['status' => 'cancelled']);
        $booking->forceFill(['google_event_id' => 'evt-1'])->saveQuietly();

        $this->mock(GoogleCalendarSyncServiceInterface::class, function ($mock) {
            $mock->shouldReceive('ensureCalendarForOperator')->once()->andReturn('cal-123');
            $mock->shouldReceive('deleteBookingEvent')->once();
            $mock->shouldNotReceive('upsertBookingEvent');
        });

        $this->artisan('calendario:sincronizar-google')->assertSuccessful();
    }

    public function test_dry_run_never_calls_the_service(): void
    {
        $this->enableSync();
        $operator = $this->sharedOperator();
        $this->bookingFor($operator);

        $this->mock(GoogleCalendarSyncServiceInterface::class, function ($mock) {
            $mock->shouldNotReceive('ensureCalendarForOperator');
            $mock->shouldNotReceive('shareCalendarWithEmail');
            $mock->shouldNotReceive('upsertBookingEvent');
            $mock->shouldNotReceive('deleteBookingEvent');
        });

        $this->artisan('calendario:sincronizar-google --dry-run')->assertSuccessful();
    }

    public function test_does_nothing_when_sync_disabled(): void
    {
        $operator = $this->sharedOperator();
        $this->bookingFor($operator);

        $this->mock(GoogleCalendarSyncServiceInterface::class, function ($mock) {
            $mock->shouldNotReceive('ensureCalendarForOperator');
            $mock->shouldNotReceive('upsertBookingEvent');
        });

        $this->artisan('calendario:sincronizar-google')->assertSuccessful();
    }

    public function test_ignores_operators_without_share_enabled(): void
    {
        $this->enableSync();
        $operator = Operator::create(['code' => 'OP-'.uniqid(), 'name' => 'Sin compartir']);
        $this->bookingFor($operator);

        $this->mock(GoogleCalendarSyncServiceInterface::class, function ($mock) {
            $mock->shouldNotReceive('ensureCalendarForOperator');
            $mock->shouldNotReceive('upsertBookingEvent');
        });

        $this->artisan('calendario:sincronizar-google')->assertSuccessful();
    }

    private function viewer(array $overrides = []): User
    {
        return User::create(array_merge([
            'name' => 'Viewer '.uniqid(),
            'first_name' => 'Viewer',
            'apellido_paterno' => 'Test',
            'email' => 'viewer-'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'is_active' => true,
            'can_login' => true,
        ], $overrides));
    }

    public function test_all_visibility_viewer_gets_shared_every_existing_calendar(): void
    {
        $this->enableSync();
        $operatorA = Operator::create(['code' => 'OP-'.uniqid(), 'name' => 'A']);
        $operatorA->forceFill(['google_calendar_id' => 'cal-A'])->save();
        $operatorB = Operator::create(['code' => 'OP-'.uniqid(), 'name' => 'B']);
        $operatorB->forceFill(['google_calendar_id' => 'cal-B'])->save();

        $this->viewer(['google_personal_email' => 'admin@example.com', 'google_calendar_visibility' => 'all']);

        $this->mock(GoogleCalendarSyncServiceInterface::class, function ($mock) {
            $mock->shouldReceive('shareCalendarWithEmail')->once()->with('cal-A', 'admin@example.com')->andReturn(true);
            $mock->shouldReceive('shareCalendarWithEmail')->once()->with('cal-B', 'admin@example.com')->andReturn(true);
        });

        $this->artisan('calendario:sincronizar-google')->assertSuccessful();
    }

    public function test_personal_visibility_viewer_only_gets_own_operator_calendar(): void
    {
        $this->enableSync();
        $operatorA = Operator::create(['code' => 'OP-'.uniqid(), 'name' => 'A']);
        $operatorA->forceFill(['google_calendar_id' => 'cal-A'])->save();
        $operatorB = Operator::create(['code' => 'OP-'.uniqid(), 'name' => 'B']);
        $operatorB->forceFill(['google_calendar_id' => 'cal-B'])->save();

        $this->viewer([
            'operator_id' => $operatorA->id,
            'google_personal_email' => 'operadorusuario@example.com',
            'google_calendar_visibility' => 'personal',
        ]);

        $this->mock(GoogleCalendarSyncServiceInterface::class, function ($mock) {
            $mock->shouldReceive('shareCalendarWithEmail')->once()->with('cal-A', 'operadorusuario@example.com')->andReturn(true);
        });

        $this->artisan('calendario:sincronizar-google')->assertSuccessful();
    }

    private function mockServicePassthrough(): void
    {
        $this->mock(GoogleCalendarSyncServiceInterface::class, function ($mock) {
            $mock->shouldReceive('ensureCalendarForOperator')->andReturn('cal-1');
            $mock->shouldReceive('shareCalendarWithEmail')->andReturn(true);
            $mock->shouldReceive('upsertBookingEvent');
            $mock->shouldReceive('deleteBookingEvent');
        });
    }

    public function test_notifies_watcher_with_all_visibility_when_a_booking_changed(): void
    {
        Mail::fake();
        $this->enableSync();
        $operator = $this->sharedOperator();
        $this->bookingFor($operator); // google_synced_at nulo => cambio "nueva"

        $this->viewer([
            'google_personal_email' => 'jefe@example.com',
            'google_calendar_visibility' => 'all',
            'google_calendar_notify_email' => true,
        ]);

        $this->mockServicePassthrough();

        $this->artisan('calendario:sincronizar-google')->assertSuccessful();

        Mail::assertSent(GoogleCalendarUpdatedMail::class, function ($mail) {
            return $mail->hasTo('jefe@example.com')
                && count($mail->changes) === 1
                && $mail->changes[0]['type'] === 'nueva'
                && $mail->changes[0]['pet'] === 'Luka';
        });
    }

    public function test_watcher_notification_falls_back_to_login_email_when_no_personal_email(): void
    {
        Mail::fake();
        $this->enableSync();
        $operator = $this->sharedOperator();
        $this->bookingFor($operator);

        $this->viewer([
            'email' => 'login-only@example.com',
            'google_calendar_visibility' => 'all',
            'google_calendar_notify_email' => true,
        ]);

        $this->mockServicePassthrough();

        $this->artisan('calendario:sincronizar-google')->assertSuccessful();

        Mail::assertSent(GoogleCalendarUpdatedMail::class, fn ($mail) => $mail->hasTo('login-only@example.com'));
    }

    public function test_personal_visibility_watcher_not_notified_for_other_operators_changes(): void
    {
        Mail::fake();
        $this->enableSync();
        $operatorA = $this->sharedOperator();
        $operatorB = $this->sharedOperator();
        $this->bookingFor($operatorB); // el cambio es de otro operador

        $this->viewer([
            'operator_id' => $operatorA->id,
            'google_personal_email' => 'op-a@example.com',
            'google_calendar_visibility' => 'personal',
            'google_calendar_notify_email' => true,
        ]);

        $this->mockServicePassthrough();

        $this->artisan('calendario:sincronizar-google')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_no_watcher_notification_when_flag_is_off(): void
    {
        Mail::fake();
        $this->enableSync();
        $operator = $this->sharedOperator();
        $this->bookingFor($operator);

        $this->viewer([
            'google_personal_email' => 'jefe@example.com',
            'google_calendar_visibility' => 'all',
            'google_calendar_notify_email' => false,
        ]);

        $this->mockServicePassthrough();

        $this->artisan('calendario:sincronizar-google')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_dry_run_does_not_notify_watchers(): void
    {
        Mail::fake();
        $this->enableSync();
        $operator = $this->sharedOperator();
        $this->bookingFor($operator);

        $this->viewer([
            'google_personal_email' => 'jefe@example.com',
            'google_calendar_visibility' => 'all',
            'google_calendar_notify_email' => true,
        ]);

        $this->mock(GoogleCalendarSyncServiceInterface::class, function ($mock) {
            $mock->shouldNotReceive('upsertBookingEvent');
        });

        $this->artisan('calendario:sincronizar-google --dry-run')->assertSuccessful();

        Mail::assertNothingSent();
    }
}
