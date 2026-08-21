<?php

namespace Tests\Feature\WhatsApp;

use App\Models\Client;
use App\Support\WhatsApp\PhoneNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhoneNormalizerTest extends TestCase
{
    use RefreshDatabase;

    public function test_ten_digit_number_gets_mx_country_code_prefixed(): void
    {
        $this->assertSame('525512345678', PhoneNormalizer::toWhatsAppNumber('55 1234 5678'));
        $this->assertSame('525512345678', PhoneNormalizer::toWhatsAppNumber('5512345678'));
    }

    public function test_numbers_already_prefixed_with_country_code_are_kept_as_is(): void
    {
        $this->assertSame('524491868912', PhoneNormalizer::toWhatsAppNumber('+52 449 186 8912'));
        $this->assertSame('524491868912', PhoneNormalizer::toWhatsAppNumber('524491868912'));
    }

    public function test_old_whatsapp_mx_mobile_prefix_521_drops_the_extra_one(): void
    {
        $this->assertSame('524491868912', PhoneNormalizer::toWhatsAppNumber('5214491868912'));
    }

    public function test_malformed_or_non_mx_numbers_are_not_recognized(): void
    {
        $this->assertNull(PhoneNormalizer::toWhatsAppNumber('123'));
        $this->assertNull(PhoneNormalizer::toWhatsAppNumber('+1 415 555 0132'));
        $this->assertNull(PhoneNormalizer::toWhatsAppNumber('555689622')); // 9 dígitos, dato incompleto
        $this->assertNull(PhoneNormalizer::toWhatsAppNumber('449123456789')); // 12 dígitos, no inicia con 52
        $this->assertNull(PhoneNormalizer::toWhatsAppNumber(null));
    }

    public function test_prefers_mobile_phone_over_others(): void
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);
        $client->phones()->create(['number' => '5511112222', 'type' => 'fixed']);
        $client->phones()->create(['number' => '5533334444', 'type' => 'mobile']);

        $this->assertSame('5533334444', PhoneNormalizer::bestPhoneFor($client->fresh(['phones'])));
    }

    public function test_falls_back_to_first_phone_when_no_mobile_registered(): void
    {
        $client = Client::create(['first_name' => 'Beto', 'apellido_paterno' => 'Soto']);
        $client->phones()->create(['number' => '5511112222', 'type' => 'fixed']);

        $this->assertSame('5511112222', PhoneNormalizer::bestPhoneFor($client->fresh(['phones'])));
    }

    public function test_prefers_the_highest_priority_mobile_when_the_client_has_more_than_one(): void
    {
        $client = Client::create(['first_name' => 'Carla', 'apellido_paterno' => 'Vega']);
        $client->phones()->create(['number' => '5522223333', 'type' => 'mobile', 'sort_order' => 1]);
        $client->phones()->create(['number' => '5511110000', 'type' => 'mobile', 'sort_order' => 0]);
        $client->phones()->create(['number' => '5599998888', 'type' => 'home', 'sort_order' => 2]);

        $this->assertSame('5511110000', PhoneNormalizer::bestPhoneFor($client->fresh(['phones'])));
    }
}
