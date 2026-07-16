<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Group;
use App\Models\Item;
use App\Models\Pet;
use App\Models\Quote;
use App\Models\Service;
use App\Models\SpaBooking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuoteGroupTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Quote Group Test',
            'first_name' => 'Quote',
            'apellido_paterno' => 'Test',
            'email' => 'quote-group-test-'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'role' => 'admin',
            'is_active' => true,
            'can_login' => true,
        ]);
    }

    private function booking(): SpaBooking
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Luka']);

        return SpaBooking::create([
            'pet_id' => $pet->id,
            'scheduled_at' => now()->addDay(),
            'status' => 'scheduled',
            'total_estimated_price' => 0,
        ]);
    }

    public function test_adding_a_group_expands_into_n_quote_items_tagged_with_group_id(): void
    {
        $booking = $this->booking();
        $service = Service::create(['code' => 'VET-'.uniqid(), 'name' => 'Veterinario', 'type' => 'extra', 'price' => 100, 'duration_minutes' => 60]);
        $item = Item::create(['name' => 'Venda', 'price' => 10]);
        $group = Group::create(['name' => 'Cirugía']);

        // El cliente manda las filas ya expandidas (como lo haría _quote_manager.blade.php al agregar un Grupo)
        $response = $this->actingAs($this->admin())->post(route('agenda.quotes.store', $booking), [
            'version_label' => 'Opción A',
            'items' => [
                ['service_id' => $service->id, 'quantity' => 0.5, 'price' => 50, 'group_id' => $group->id],
                ['item_id' => $item->id, 'quantity' => 5, 'price' => 10, 'group_id' => $group->id],
            ],
        ]);

        $response->assertRedirect();
        $quote = Quote::firstOrFail();
        $this->assertCount(2, $quote->items);
        $this->assertTrue($quote->items->every(fn ($item) => $item->group_id === $group->id));
        $this->assertEquals(75.0, (float) $quote->total_amount); // 0.5*50=25 + 5*10=50
    }

    public function test_quote_total_multiplies_quantity_by_unit_price(): void
    {
        $booking = $this->booking();
        $service = Service::create(['code' => 'VET-'.uniqid(), 'name' => 'Veterinario', 'type' => 'extra', 'price' => 100, 'duration_minutes' => 60]);

        $response = $this->actingAs($this->admin())->post(route('agenda.quotes.store', $booking), [
            'items' => [
                ['service_id' => $service->id, 'quantity' => 0.5],
            ],
        ]);

        $response->assertRedirect();
        $quote = Quote::firstOrFail();
        $this->assertEquals(50.0, (float) $quote->total_amount); // 0.5 * 100 (precio del catálogo, sin price_override)
    }

    public function test_accepting_a_quote_freezes_item_lines_into_spa_booking_items(): void
    {
        $booking = $this->booking();
        $service = Service::create(['code' => 'VET-'.uniqid(), 'name' => 'Veterinario', 'type' => 'extra', 'price' => 100, 'duration_minutes' => 60]);
        $item = Item::create(['name' => 'Venda', 'price' => 10]);
        $group = Group::create(['name' => 'Cirugía']);

        $this->actingAs($this->admin())->post(route('agenda.quotes.store', $booking), [
            'items' => [
                ['service_id' => $service->id, 'quantity' => 1, 'price' => 100],
                ['item_id' => $item->id, 'quantity' => 5, 'price' => 10, 'group_id' => $group->id],
            ],
        ]);
        $quote = Quote::firstOrFail();

        $response = $this->actingAs($this->admin())->post(route('agenda.quotes.accept', [$booking, $quote]), []);
        $response->assertRedirect();

        $booking->refresh();
        $this->assertCount(1, $booking->services);
        $this->assertCount(1, $booking->items);

        $bookingItem = $booking->items->first();
        $this->assertSame($item->id, $bookingItem->item_id);
        $this->assertEquals(5.0, (float) $bookingItem->quantity);
        $this->assertEquals(50.0, (float) $bookingItem->current_price); // 5 * 10
        $this->assertSame($group->id, $bookingItem->group_id);

        $this->assertSame('work_order', $booking->status);
    }
}
