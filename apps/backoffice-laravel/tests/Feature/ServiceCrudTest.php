<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ExecutedService;
use App\Models\ExecutedServiceItem;
use App\Models\Group;
use App\Models\GroupComponent;
use App\Models\Pet;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Service;
use App\Models\SpaBooking;
use App\Models\SpaBookingService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * SYNC-104/ZEUS-027: `ServiceController::destroy()` no tenía ningún test hasta ahora. `quote_items.service_id`,
 * `spa_booking_services.service_id` y `executed_service_items.service_id` son todas
 * `cascadeOnDelete()`: borrar un servicio que ya apareció en un presupuesto, una cita o un
 * servicio ejecutado real borraría esas líneas históricas en cascada. `destroy()` ahora lo
 * suspende (`is_active = false`, ya lo saca de todos los selectores de alta) en vez de borrar
 * cuando hay cualquier uso real.
 */
class ServiceCrudTest extends TestCase
{
    use RefreshDatabase;

    private function userWithPermissions(array $permissions): User
    {
        $user = User::create([
            'name' => 'Catalogo Test',
            'first_name' => 'Catalogo',
            'apellido_paterno' => 'Test',
            'email' => 'catalogo-service-test-'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'is_active' => true,
            'can_login' => true,
        ]);

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $user->givePermissionTo($permissions);

        return $user;
    }

    private function service(): Service
    {
        return Service::create([
            'code' => 'SVC-'.uniqid(), 'type' => 'spa', 'name' => 'Baño', 'price' => 100,
            'duration_minutes' => 30, 'is_active' => true,
        ]);
    }

    private function pet(): Pet
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);

        return Pet::create(['client_id' => $client->id, 'name' => 'Luka']);
    }

    public function test_deleting_a_service_with_no_usage_deletes_it_for_real(): void
    {
        $user = $this->userWithPermissions(['eliminar catalogo_servicios']);
        $service = $this->service();

        $response = $this->actingAs($user)->delete(route('services.destroy', $service));

        $response->assertRedirect(route('services.index'));
        $this->assertModelMissing($service);
    }

    public function test_deleting_a_service_referenced_by_a_quote_item_suspends_it_instead_of_deleting(): void
    {
        $user = $this->userWithPermissions(['eliminar catalogo_servicios']);
        $service = $this->service();
        $pet = $this->pet();
        $booking = SpaBooking::create(['pet_id' => $pet->id, 'scheduled_at' => now(), 'duration_minutes' => 30, 'status' => 'scheduled', 'total_estimated_price' => 100]);
        $quote = Quote::create(['spa_booking_id' => $booking->id, 'version_label' => 'v1', 'status' => 'draft', 'total_amount' => 100]);
        $quoteItem = QuoteItem::create(['quote_id' => $quote->id, 'service_id' => $service->id, 'quantity' => 1]);

        $response = $this->actingAs($user)->delete(route('services.destroy', $service));

        $response->assertRedirect(route('services.index'));
        $this->assertModelExists($service);
        $this->assertFalse($service->fresh()->is_active);
        $this->assertModelExists($quoteItem);
    }

    public function test_deleting_a_service_referenced_by_a_spa_booking_service_suspends_it_instead_of_deleting(): void
    {
        $user = $this->userWithPermissions(['eliminar catalogo_servicios']);
        $service = $this->service();
        $pet = $this->pet();
        $booking = SpaBooking::create(['pet_id' => $pet->id, 'scheduled_at' => now(), 'duration_minutes' => 30, 'status' => 'completed', 'total_estimated_price' => 100]);
        $bookingService = SpaBookingService::create(['spa_booking_id' => $booking->id, 'service_id' => $service->id, 'current_price' => 100]);

        $response = $this->actingAs($user)->delete(route('services.destroy', $service));

        $response->assertRedirect(route('services.index'));
        $this->assertModelExists($service);
        $this->assertFalse($service->fresh()->is_active);
        $this->assertModelExists($bookingService);
    }

    public function test_deleting_a_service_referenced_by_an_executed_service_item_suspends_it_instead_of_deleting(): void
    {
        $user = $this->userWithPermissions(['eliminar catalogo_servicios']);
        $service = $this->service();
        $pet = $this->pet();
        $booking = SpaBooking::create(['pet_id' => $pet->id, 'scheduled_at' => now(), 'duration_minutes' => 30, 'status' => 'completed', 'total_estimated_price' => 100]);
        $executed = ExecutedService::create(['spa_booking_id' => $booking->id, 'pet_id' => $pet->id, 'final_price' => 100, 'executed_at' => now()]);
        $executedItem = ExecutedServiceItem::create([
            'executed_service_id' => $executed->id, 'service_id' => $service->id,
            'service_name_snapshot' => $service->name, 'charged_price' => 100,
        ]);

        $response = $this->actingAs($user)->delete(route('services.destroy', $service));

        $response->assertRedirect(route('services.index'));
        $this->assertModelExists($service);
        $this->assertFalse($service->fresh()->is_active);
        $this->assertModelExists($executedItem);
    }

    /**
     * Ser componente de un Grupo también cuenta como "en uso" — antes bloqueaba con un mensaje de
     * error aparte, ahora se unifica con el resto: se suspende en vez de bloquear.
     */
    public function test_deleting_a_service_that_is_a_group_component_suspends_it_instead_of_deleting(): void
    {
        $user = $this->userWithPermissions(['eliminar catalogo_servicios']);
        $service = $this->service();
        $group = Group::create(['name' => 'Combo baño', 'is_active' => true]);
        $component = GroupComponent::create(['group_id' => $group->id, 'service_id' => $service->id, 'quantity' => 1]);

        $response = $this->actingAs($user)->delete(route('services.destroy', $service));

        $response->assertRedirect(route('services.index'));
        $this->assertModelExists($service);
        $this->assertFalse($service->fresh()->is_active);
        $this->assertModelExists($component);
    }
}
