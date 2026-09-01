<?php

namespace Tests\Feature\GoogleCalendar;

use App\Domain\GoogleCalendar\Services\GoogleCalendarSyncService;
use App\Support\SystemSettings\SystemSettings;
use Google\Service\Calendar\AclRule;
use Google\Service\Calendar as GoogleCalendarApi;
use Mockery;
use Tests\TestCase;

/**
 * Al compartir un calendario de operador con un email (cada corrida del cron), EstetiCAN
 * NO debe pedirle a Google que mande el correo "se compartió un calendario contigo":
 * `acl.insert` se llama con `sendNotifications = false`. Los avisos de cambios de eventos
 * posteriores son ajuste del destinatario en su propio Google Calendar — fuera del alcance
 * de EstetiCAN (ver la nota en la pantalla de edición de usuario).
 */
class ShareCalendarSendNotificationsTest extends TestCase
{
    public function test_share_calendar_passes_send_notifications_false(): void
    {
        $acl = Mockery::mock();
        $acl->shouldReceive('insert')
            ->once()
            ->with('cal-1', Mockery::type(AclRule::class), ['sendNotifications' => false])
            ->andReturn(new \stdClass);

        $fakeApi = Mockery::mock(GoogleCalendarApi::class);
        $fakeApi->acl = $acl;

        $service = new class(app(SystemSettings::class), $fakeApi) extends GoogleCalendarSyncService
        {
            public function __construct(SystemSettings $settings, private readonly GoogleCalendarApi $fake)
            {
                parent::__construct($settings);
            }

            protected function client(): ?GoogleCalendarApi
            {
                return $this->fake;
            }
        };

        $this->assertTrue($service->shareCalendarWithEmail('cal-1', 'viewer@example.com'));
    }
}
