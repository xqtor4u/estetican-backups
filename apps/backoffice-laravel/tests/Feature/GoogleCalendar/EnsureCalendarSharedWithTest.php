<?php

namespace Tests\Feature\GoogleCalendar;

use App\Domain\GoogleCalendar\Services\GoogleCalendarSyncService;
use App\Support\SystemSettings\SystemSettings;
use Google\Service\Calendar\Acl as GoogleAcl;
use Google\Service\Calendar\AclRule;
use Google\Service\Calendar as GoogleCalendarApi;
use Mockery;
use Tests\TestCase;

/**
 * `ensureCalendarSharedWith()` evita el 403 "Calendar usage limits exceeded" que provoca
 * repetir `acl.insert` en cada corrida del cron: lee la ACL del calendario una vez y solo
 * inserta si el email no está ya en la lista.
 */
class EnsureCalendarSharedWithTest extends TestCase
{
    private function serviceWith(GoogleCalendarApi $fake): GoogleCalendarSyncService
    {
        return new class($fake) extends GoogleCalendarSyncService
        {
            public function __construct(private readonly GoogleCalendarApi $fake)
            {
                parent::__construct(app(SystemSettings::class));
            }

            protected function client(): ?GoogleCalendarApi
            {
                return $this->fake;
            }
        };
    }

    private function aclWith(array $userEmails): GoogleAcl
    {
        $items = [];
        foreach ($userEmails as $email) {
            $items[] = new AclRule([
                'role' => 'reader',
                'scope' => ['type' => 'user', 'value' => $email],
            ]);
        }

        return new GoogleAcl(['items' => $items]);
    }

    public function test_skips_insert_when_email_already_in_acl(): void
    {
        $acl = Mockery::mock();
        $acl->shouldReceive('listAcl')->once()->with('cal-1')->andReturn($this->aclWith(['viewer@example.com']));
        $acl->shouldReceive('insert')->never();

        $fake = Mockery::mock(GoogleCalendarApi::class);
        $fake->acl = $acl;

        $this->assertTrue($this->serviceWith($fake)->ensureCalendarSharedWith('cal-1', 'Viewer@Example.com'));
    }

    public function test_inserts_when_email_missing_from_acl(): void
    {
        $acl = Mockery::mock();
        $acl->shouldReceive('listAcl')->once()->with('cal-1')->andReturn($this->aclWith(['otro@example.com']));
        $acl->shouldReceive('insert')
            ->once()
            ->with('cal-1', Mockery::type(AclRule::class), ['sendNotifications' => false])
            ->andReturn(new \stdClass);

        $fake = Mockery::mock(GoogleCalendarApi::class);
        $fake->acl = $acl;

        $this->assertTrue($this->serviceWith($fake)->ensureCalendarSharedWith('cal-1', 'viewer@example.com'));
    }

    public function test_reads_acl_only_once_per_calendar(): void
    {
        $acl = Mockery::mock();
        $acl->shouldReceive('listAcl')->once()->with('cal-1')->andReturn($this->aclWith(['a@example.com', 'b@example.com']));
        $acl->shouldReceive('insert')->never();

        $fake = Mockery::mock(GoogleCalendarApi::class);
        $fake->acl = $acl;

        $service = $this->serviceWith($fake);
        $service->ensureCalendarSharedWith('cal-1', 'a@example.com');
        $service->ensureCalendarSharedWith('cal-1', 'b@example.com');
    }

    public function test_falls_back_to_insert_when_acl_list_fails(): void
    {
        $acl = Mockery::mock();
        $acl->shouldReceive('listAcl')->once()->with('cal-1')->andThrow(new \RuntimeException('quotaExceeded'));
        $acl->shouldReceive('insert')->once()->andReturn(new \stdClass);

        $fake = Mockery::mock(GoogleCalendarApi::class);
        $fake->acl = $acl;

        $this->assertTrue($this->serviceWith($fake)->ensureCalendarSharedWith('cal-1', 'viewer@example.com'));
    }
}
