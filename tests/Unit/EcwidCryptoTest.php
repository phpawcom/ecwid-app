<?php

namespace Tests\Unit;

use App\Ecwid\EcwidCrypto;
use PHPUnit\Framework\TestCase;

class EcwidCryptoTest extends TestCase
{
    private const SECRET = 'RVAa4VANXwEVui6gasscax8x93yUBdPr';

    public function test_it_decrypts_gcm_enc_data(): void
    {
        $payload = ['storeId' => 123, 'token' => 'secret_x', 'note' => str_repeat('ü', 50)];

        $this->assertSame($payload, EcwidCrypto::decryptGcm(self::SECRET, EcwidCrypto::encrypt(self::SECRET, $payload)));
    }

    public function test_it_decrypts_legacy_cbc_data(): void
    {
        $payload = ['storeId' => 123];

        $this->assertSame($payload, EcwidCrypto::decryptCbc(self::SECRET, EcwidCrypto::encrypt(self::SECRET, $payload, 'aes-128-cbc')));
    }

    public function test_payment_request_prefers_enc_data_and_falls_back_to_data(): void
    {
        $gcm = EcwidCrypto::encrypt(self::SECRET, ['v' => 'gcm']);
        $cbc = EcwidCrypto::encrypt(self::SECRET, ['v' => 'cbc'], 'aes-128-cbc');

        $this->assertSame(['v' => 'gcm'], EcwidCrypto::decryptPaymentRequest(self::SECRET, $gcm, $cbc));
        $this->assertSame(['v' => 'cbc'], EcwidCrypto::decryptPaymentRequest(self::SECRET, 'garbage', $cbc));
        $this->assertSame(['v' => 'cbc'], EcwidCrypto::decryptPaymentRequest(self::SECRET, null, $cbc));
    }

    public function test_wrong_secret_or_tampered_data_is_rejected(): void
    {
        $gcm = EcwidCrypto::encrypt(self::SECRET, ['v' => 1]);

        $this->assertNull(EcwidCrypto::decryptGcm('another-secret-123456', $gcm));
        $this->assertNull(EcwidCrypto::decryptGcm(self::SECRET, substr($gcm, 0, -4).'AAAA'));
        $this->assertNull(EcwidCrypto::decryptPaymentRequest(self::SECRET, null, null));
    }
}
