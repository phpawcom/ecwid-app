<?php

namespace Tests\Unit;

use App\Ecwid\PaymentRequest;
use PHPUnit\Framework\TestCase;

class PaymentRequestTest extends TestCase
{
    public function test_merchant_settings_unwrap_values_saved_as_json(): void
    {
        $request = new PaymentRequest(['merchantAppSettings' => [
            'secret_key' => '{"value":"sk_123"}',
            'exchange_rate' => '{"value":""}',
            'sandbox' => 'true',
            'note' => '{"value": "x", "other": 1}',
        ]]);

        $this->assertSame([
            'secret_key' => 'sk_123',
            'exchange_rate' => '',
            'sandbox' => 'true',
            'note' => '{"value": "x", "other": 1}',
        ], $request->merchantSettings());
    }
}
