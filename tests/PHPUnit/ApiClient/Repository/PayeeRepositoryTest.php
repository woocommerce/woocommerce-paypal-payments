<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Repository;

use WooCommerce\PayPalCommerce\ApiClient\Config\Config;
use WooCommerce\PayPalCommerce\ApiClient\TestCase;
use Mockery;

class PayeeRepositoryTest extends TestCase
{

    public function testDefault()
    {
        $merchantEmail = 'merchant_email';
        $merchantId = 'merchant_id';
        $testee = new PayeeRepository($merchantEmail, $merchantId);
        $payee = $testee->payee();
        $this->assertEquals($merchantId, $payee->merchant_id());
        $this->assertEquals($merchantEmail, $payee->email());
    }
}
