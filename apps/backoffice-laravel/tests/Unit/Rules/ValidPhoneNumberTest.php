<?php

namespace Tests\Unit\Rules;

use App\Rules\ValidPhoneNumber;
use PHPUnit\Framework\TestCase;

class ValidPhoneNumberTest extends TestCase
{
    private function failsFor(string $value, ?ValidPhoneNumber $rule = null): bool
    {
        $failed = false;
        ($rule ?? new ValidPhoneNumber())->validate('number', $value, function () use (&$failed) {
            $failed = true;
        });

        return $failed;
    }

    public function test_default_accepts_raw_ten_digits(): void
    {
        $this->assertFalse($this->failsFor('8112345678'));
    }

    public function test_default_accepts_ten_digits_formatted_with_spaces_or_dashes(): void
    {
        $this->assertFalse($this->failsFor('81 1234 5678'));
        $this->assertFalse($this->failsFor('811-234-5678'));
        $this->assertFalse($this->failsFor('(81) 1234 5678'));
    }

    public function test_default_rejects_fewer_than_ten_digits(): void
    {
        $this->assertTrue($this->failsFor('811234567'));
    }

    public function test_default_rejects_more_than_ten_digits_including_country_code(): void
    {
        $this->assertTrue($this->failsFor('528112345678'));
    }

    public function test_default_rejects_empty_or_non_numeric(): void
    {
        $this->assertTrue($this->failsFor(''));
        $this->assertTrue($this->failsFor('abcdefghij'));
    }

    /**
     * Rango internacional (E.164, activado vía "Permitir código de país" en Configuración →
     * Clientes): entre 8 y 15 dígitos en total, código de país incluido.
     */
    public function test_international_range_accepts_a_number_with_country_code(): void
    {
        $rule = new ValidPhoneNumber(minDigits: 8, maxDigits: 15);

        $this->assertFalse($this->failsFor('528112345678', $rule)); // MX +52, 12 dígitos
        $this->assertFalse($this->failsFor('+1 213 555 0182', $rule)); // US/CA +1, 11 dígitos
        $this->assertFalse($this->failsFor('34911234567', $rule)); // España +34, 11 dígitos
    }

    public function test_international_range_rejects_below_the_minimum(): void
    {
        $rule = new ValidPhoneNumber(minDigits: 8, maxDigits: 15);

        $this->assertTrue($this->failsFor('1234567', $rule)); // 7 dígitos
    }

    public function test_international_range_rejects_above_the_e164_maximum(): void
    {
        $rule = new ValidPhoneNumber(minDigits: 8, maxDigits: 15);

        $this->assertTrue($this->failsFor('1234567890123456', $rule)); // 16 dígitos
    }

    public function test_error_message_mentions_exact_count_when_min_equals_max(): void
    {
        $message = null;
        (new ValidPhoneNumber())->validate('number', '123', function (string $m) use (&$message) {
            $message = $m;
        });

        $this->assertSame('El teléfono debe tener 10 dígitos.', $message);
    }

    public function test_error_message_mentions_range_when_min_differs_from_max(): void
    {
        $message = null;
        (new ValidPhoneNumber(minDigits: 8, maxDigits: 15))->validate('number', '123', function (string $m) use (&$message) {
            $message = $m;
        });

        $this->assertSame('El teléfono debe tener entre 8 y 15 dígitos.', $message);
    }
}
