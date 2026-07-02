<?php

namespace App\Support\SystemSettings;

use Carbon\Carbon;

class BusinessHours
{
    public function __construct(private SystemSettings $settings) {}

    public function openingTime(): string
    {
        return (string) ($this->settings->all()['booking_opening_time'] ?? '09:00');
    }

    public function closingTime(): string
    {
        return (string) ($this->settings->all()['booking_closing_time'] ?? '19:00');
    }

    public function isWithin(Carbon $dateTime): bool
    {
        $time = $dateTime->format('H:i');

        return $time >= $this->openingTime() && $time <= $this->closingTime();
    }
}
