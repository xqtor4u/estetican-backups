<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\SystemSettings\SystemSettings;
use Illuminate\Http\JsonResponse;

class SettingController extends Controller
{
    public function booking(SystemSettings $settings): JsonResponse
    {
        $all = $settings->all();

        return response()->json([
            'grace_minutes' => (int) ($all['booking_grace_minutes'] ?? 15),
            'opening_time' => (string) ($all['booking_opening_time'] ?? '09:00'),
            'closing_time' => (string) ($all['booking_closing_time'] ?? '19:00'),
        ]);
    }

    public function photos(SystemSettings $settings): JsonResponse
    {
        $all = $settings->all();

        return response()->json([
            'watermark_enabled' => (bool) ($all['photo_watermark_enabled'] ?? false),
        ]);
    }
}
