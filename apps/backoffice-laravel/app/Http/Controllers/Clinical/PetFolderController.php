<?php

namespace App\Http\Controllers\Clinical;

use App\Http\Controllers\Controller;
use App\Models\Pet;
use App\Support\SystemSettings\SystemSettings;
use Illuminate\View\View;

class PetFolderController extends Controller
{
    public function __construct(private SystemSettings $settingsService) {}

    /**
     * Ficha/carpeta imprimible para entregar o enviar a un veterinario externo:
     * identidad de mascota + dueño, alergias/condiciones activas, vacunas y últimas visitas.
     */
    public function print(Pet $pet): View
    {
        $all = $this->settingsService->all();
        $settings = [
            'branding' => [
                'brand_business_name' => $all['brand_business_name'] ?? 'EstetiCAN',
                'brand_logo_print' => $all['brand_logo_print'] ?? $all['brand_logo_web'] ?? null,
            ],
        ];

        $pet->load([
            'client:id,first_name,apellido_paterno,apellido_materno,email',
            'client.phones',
            'allergies' => fn ($q) => $q->where('is_active', true),
            'conditions' => fn ($q) => $q->whereIn('status', ['active', 'controlled', 'chronic_monitoring']),
            'vaccinations' => fn ($q) => $q->orderByDesc('expires_at'),
            'clinicalVisits' => fn ($q) => $q->orderByDesc('visited_at')->limit(5),
        ]);

        return view('clinical.pets.folder-print', compact('pet', 'settings'));
    }
}
