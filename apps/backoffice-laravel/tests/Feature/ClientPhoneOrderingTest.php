<?php

namespace Tests\Feature;

use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

/**
 * El vocabulario de tipo de teléfono pasó de mobile/fixed a mobile/home/work/other, y cada
 * cliente puede tener varios teléfonos ordenados por importancia (sort_order) — el primero de
 * tipo "mobile" en ese orden es el que usa WhatsApp/SMS (PhoneNormalizerTest cubre ese caso).
 */
class ClientPhoneOrderingTest extends TestCase
{
    use CreatesAdminUser;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->createAdminUser());
    }

    public function test_web_store_accepts_the_four_phone_types_and_persists_submission_order(): void
    {
        $response = $this->post(route('clients.store'), [
            'first_name' => 'Renata',
            'apellido_paterno' => 'Vidal',
            'phones' => [
                ['type' => 'work', 'number' => '8110000001'],
                ['type' => 'mobile', 'number' => '8110000002'],
                ['type' => 'home', 'number' => '8110000003'],
                ['type' => 'other', 'number' => '8110000004'],
            ],
        ]);

        $response->assertRedirect(route('clients.index'));

        $client = Client::query()->where('first_name', 'Renata')->firstOrFail();
        $phones = $client->phones()->orderBy('sort_order')->get();

        $this->assertSame(['8110000001', '8110000002', '8110000003', '8110000004'], $phones->pluck('number')->all());
        $this->assertSame(['work', 'mobile', 'home', 'other'], $phones->pluck('type')->all());
        $this->assertSame([0, 1, 2, 3], $phones->pluck('sort_order')->all());
    }

    public function test_web_store_rejects_a_phone_type_outside_the_fixed_vocabulary(): void
    {
        $response = $this->post(route('clients.store'), [
            'first_name' => 'Ignacio',
            'apellido_paterno' => 'Solis',
            'phones' => [
                ['type' => 'fixed', 'number' => '8110000005'],
            ],
        ]);

        $response->assertSessionHasErrors('phones.0.type');
    }

    public function test_web_store_rejects_a_phone_number_that_is_not_ten_digits(): void
    {
        $response = $this->post(route('clients.store'), [
            'first_name' => 'Corto',
            'phones' => [
                ['type' => 'mobile', 'number' => '81123'],
            ],
        ]);

        $response->assertSessionHasErrors('phones.0.number');
    }

    public function test_web_store_accepts_a_formatted_ten_digit_number_and_persists_the_extension(): void
    {
        $response = $this->post(route('clients.store'), [
            'first_name' => 'Formateado',
            'apellido_paterno' => 'Duran',
            'phones' => [
                ['type' => 'work', 'number' => '81 1234 5678', 'extension' => '105'],
            ],
        ]);

        $response->assertRedirect(route('clients.index'));

        $client = Client::query()->where('first_name', 'Formateado')->firstOrFail();
        $phone = $client->phones()->firstOrFail();

        $this->assertSame('81 1234 5678', $phone->number);
        $this->assertSame('105', $phone->extension);
    }

    public function test_web_store_rejects_a_non_numeric_extension(): void
    {
        $response = $this->post(route('clients.store'), [
            'first_name' => 'ExtMala',
            'phones' => [
                ['type' => 'work', 'number' => '8110000006', 'extension' => 'abc'],
            ],
        ]);

        $response->assertSessionHasErrors('phones.0.extension');
    }

    public function test_web_update_reassigns_sort_order_to_match_the_reordered_submission(): void
    {
        $client = Client::create(['first_name' => 'Paola', 'apellido_paterno' => 'Nunez']);
        $first = $client->phones()->create(['number' => '8110000010', 'type' => 'mobile', 'sort_order' => 0]);
        $second = $client->phones()->create(['number' => '8110000011', 'type' => 'home', 'sort_order' => 1]);

        // Simula el resultado de apretar "subir" sobre el segundo teléfono: el navegador manda
        // los campos en el nuevo orden visual, con los mismos IDs.
        $response = $this->put(route('clients.update', $client), [
            'first_name' => 'Paola',
            'phones' => [
                ['id' => $second->id, 'type' => 'home', 'number' => '8110000011'],
                ['id' => $first->id, 'type' => 'mobile', 'number' => '8110000010'],
            ],
        ]);

        $response->assertRedirect(route('clients.edit', $client));

        $this->assertSame(0, $second->fresh()->sort_order);
        $this->assertSame(1, $first->fresh()->sort_order);
        $this->assertSame(['8110000011', '8110000010'], $client->phones()->orderBy('sort_order')->pluck('number')->all());
    }

    public function test_client_phones_relationship_is_ordered_by_sort_order(): void
    {
        $client = Client::create(['first_name' => 'Hugo', 'apellido_paterno' => 'Leal']);
        $client->phones()->create(['number' => 'C', 'type' => 'other', 'sort_order' => 2]);
        $client->phones()->create(['number' => 'A', 'type' => 'mobile', 'sort_order' => 0]);
        $client->phones()->create(['number' => 'B', 'type' => 'home', 'sort_order' => 1]);

        $this->assertSame(['A', 'B', 'C'], $client->fresh()->phones->pluck('number')->all());
    }

    public function test_api_store_accepts_multiple_phones_with_type_and_order(): void
    {
        $headers = $this->createAdminAuthHeader();

        $response = $this->withHeaders($headers)->postJson('/api/clients', [
            'first_name' => 'Dario',
            'phones' => [
                ['type' => 'home', 'number' => '8110000020'],
                ['type' => 'mobile', 'number' => '8110000021'],
            ],
        ]);

        $response->assertCreated();

        $client = Client::query()->where('first_name', 'Dario')->firstOrFail();
        $phones = $client->phones()->orderBy('sort_order')->get();

        $this->assertSame(['8110000020', '8110000021'], $phones->pluck('number')->all());
        $this->assertSame(['home', 'mobile'], $phones->pluck('type')->all());
    }

    public function test_api_store_rejects_a_phone_number_that_is_not_ten_digits(): void
    {
        $headers = $this->createAdminAuthHeader();

        $response = $this->withHeaders($headers)->postJson('/api/clients', [
            'first_name' => 'CortoApi',
            'phones' => [
                ['type' => 'mobile', 'number' => '811'],
            ],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('phones.0.number');
    }

    public function test_api_store_rejects_a_legacy_phone_that_is_not_ten_digits(): void
    {
        $headers = $this->createAdminAuthHeader();

        $response = $this->withHeaders($headers)->postJson('/api/clients', [
            'first_name' => 'CortoLegado',
            'phone' => '811',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('phone');
    }

    public function test_api_store_persists_the_extension(): void
    {
        $headers = $this->createAdminAuthHeader();

        $response = $this->withHeaders($headers)->postJson('/api/clients', [
            'first_name' => 'ConExtension',
            'phones' => [
                ['type' => 'work', 'number' => '8110000022', 'extension' => '210'],
            ],
        ]);

        $response->assertCreated();

        $client = Client::query()->where('first_name', 'ConExtension')->firstOrFail();
        $this->assertSame('210', $client->phones()->firstOrFail()->extension);
    }

    public function test_api_store_still_accepts_the_legacy_single_phone_field(): void
    {
        $headers = $this->createAdminAuthHeader();

        $response = $this->withHeaders($headers)->postJson('/api/clients', [
            'first_name' => 'Legado',
            'phone' => '8110000030',
        ]);

        $response->assertCreated();

        $client = Client::query()->where('first_name', 'Legado')->firstOrFail();
        $this->assertSame('8110000030', $client->phones()->first()->number);
        $this->assertSame('mobile', $client->phones()->first()->type);
    }

    public function test_api_store_requires_either_phone_or_phones(): void
    {
        $headers = $this->createAdminAuthHeader();

        $response = $this->withHeaders($headers)->postJson('/api/clients', [
            'first_name' => 'SinTelefono',
        ]);

        $response->assertUnprocessable();
    }

    public function test_api_update_replaces_phones_preserving_the_sent_order_and_accepts_other_type(): void
    {
        $client = Client::create(['first_name' => 'Marina', 'apellido_paterno' => 'Cruz']);
        $client->phones()->create(['number' => '8110000040', 'type' => 'mobile', 'sort_order' => 0]);

        $headers = $this->createAdminAuthHeader();

        $response = $this->withHeaders($headers)->patchJson("/api/clients/{$client->id}", [
            'phones' => [
                ['type' => 'other', 'number' => '8110000041'],
                ['type' => 'mobile', 'number' => '8110000042'],
            ],
        ]);

        $response->assertOk();

        $phones = $client->phones()->orderBy('sort_order')->get();
        $this->assertSame(['8110000041', '8110000042'], $phones->pluck('number')->all());
        $this->assertSame(['other', 'mobile'], $phones->pluck('type')->all());
    }

    public function test_api_update_rejects_a_phone_number_that_is_not_ten_digits(): void
    {
        $client = Client::create(['first_name' => 'Ursula', 'apellido_paterno' => 'Reyes']);
        $client->phones()->create(['number' => '8110000050', 'type' => 'mobile', 'sort_order' => 0]);

        $headers = $this->createAdminAuthHeader();

        $response = $this->withHeaders($headers)->patchJson("/api/clients/{$client->id}", [
            'phones' => [
                ['type' => 'mobile', 'number' => '123'],
            ],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('phones.0.number');
    }

    public function test_api_update_persists_the_extension(): void
    {
        $client = Client::create(['first_name' => 'Vicente', 'apellido_paterno' => 'Ibarra']);
        $client->phones()->create(['number' => '8110000051', 'type' => 'work', 'sort_order' => 0]);

        $headers = $this->createAdminAuthHeader();

        $response = $this->withHeaders($headers)->patchJson("/api/clients/{$client->id}", [
            'phones' => [
                ['type' => 'work', 'number' => '8110000051', 'extension' => '77'],
            ],
        ]);

        $response->assertOk();
        $this->assertSame('77', $client->phones()->firstOrFail()->extension);
    }
}
