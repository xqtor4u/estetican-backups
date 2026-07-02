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

    public function test_non_ten_digit_numbers_are_not_recognized(): void
    {
        $this->assertNull(PhoneNormalizer::toWhatsAppNumber('123'));
        $this->assertNull(PhoneNormalizer::toWhatsAppNumber('+1 415 555 0132'));
        $this->assertNull(PhoneNormalizer::toWhatsAppNumber(null));
    }

    public function test_prefers_mobile_phone_over_others(): void
    {
        $client = Client::create(['first_name' => 'Ana', 'last_name' => 'Ruiz']);
        $client->phones()->create(['number' => '5511112222', 'type' => 'fixed']);
        $client->phones()->create(['number' => '5533334444', 'type' => 'mobile']);

        $this->assertSame('5533334444', PhoneNormalizer::bestPhoneFor($client->fresh(['phones'])));
    }

    public function test_falls_back_to_first_phone_when_no_mobile_registered(): void
    {
        $client = Client::create(['first_name' => 'Beto', 'last_name' => 'Soto']);
        $client->phones()->create(['number' => '5511112222', 'type' => 'fixed']);

        $this->assertSame('5511112222', PhoneNormalizer::bestPhoneFor($client->fresh(['phones'])));
    }
}
