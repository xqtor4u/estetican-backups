<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\SystemSettings\SystemSettings;

class SettingController extends Controller
{
    public function booking(SystemSettings $settings): \Illuminate\Http\JsonResponse
    {
        $all = $settings->all();
        return response()->json([
            'grace_minutes' => (int) ($all['booking_grace_minutes'] ?? 15),
        ]);
    }
}
