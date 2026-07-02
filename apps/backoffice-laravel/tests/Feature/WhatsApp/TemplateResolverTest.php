<?php

namespace Tests\Feature\WhatsApp;

use App\Models\Client;
use App\Models\Pet;
use App\Models\Service;
use App\Models\SpaBooking;
use App\Support\WhatsApp\TemplateResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TemplateResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_all_placeholders_from_booking_data(): void
    {
        $client = Client::create(['first_name' => 'Ana', 'last_name' => 'Ruiz']);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Luka']);
        $service = Service::create(['code' => 'BC01', 'name' => 'Baño y corte', 'type' => 'spa', 'price' => 250, 'duration_minutes' => 60]);

        $booking = SpaBooking::create([
            'pet_id' => $pet->id,
            'scheduled_at' => '2026-07-10 15:30:00',
            'status' => 'scheduled',
            'total_estimated_price' => 250,
        ]);
        $booking->services()->create(['service_id' => $service->id, 'current_price' => 250]);

        $resolved = TemplateResolver::resolve(
            'Hola {cliente}, recordamos la cita de {mascota} ({servicio}) el {fecha} a las {hora}.',
            $booking->fresh(['pet.client', 'services.service']),
            'd/m/Y',
            'H:i',
        );

        $this->assertSame(
            'Hola Ana Ruiz, recordamos la cita de Luka (Baño y corte) el 10/07/2026 a las 15:30.',
            $resolved,
        );
    }
}
