<?php

namespace Tests\Feature\Planning;

use App\Support\SystemSettings\BusinessHours;
use App\Support\SystemSettings\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BusinessHoursTest extends TestCase
{
    use RefreshDatabase;

    public function test_defaults_to_nine_to_nineteen_when_unconfigured(): void
    {
        $hours = new BusinessHours(app(SystemSettings::class));

        $this->assertSame('09:00', $hours->openingTime());
        $this->assertSame('19:00', $hours->closingTime());
    }

    public function test_time_within_business_hours_is_accepted(): void
    {
        $hours = new BusinessHours(app(SystemSettings::class));

        $this->assertTrue($hours->isWithin(Carbon::parse('2026-07-10 09:00')));
        $this->assertTrue($hours->isWithin(Carbon::parse('2026-07-10 13:30')));
        $this->assertTrue($hours->isWithin(Carbon::parse('2026-07-10 19:00')));
    }

    public function test_time_outside_business_hours_is_rejected(): void
    {
        $hours = new BusinessHours(app(SystemSettings::class));

        $this->assertFalse($hours->isWithin(Carbon::parse('2026-07-10 08:59')));
        $this->assertFalse($hours->isWithin(Carbon::parse('2026-07-10 19:01')));
        $this->assertFalse($hours->isWithin(Carbon::parse('2026-07-10 23:30')));
    }
}
