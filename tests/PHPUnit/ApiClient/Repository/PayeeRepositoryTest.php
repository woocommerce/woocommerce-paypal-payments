<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Repository;

use Inpsyde\PayPalCommerce\ApiClient\Config\Config;
use Inpsyde\PayPalCommerce\ApiClient\TestCase;
use Mockery;

class PayeeRepositoryTest extends TestCase
{

    public function testDefault()
    {
        $merchantEmail = 'merchant_email';
        $merchantId = 'merchant_id';
        $testee = new PayeeRepository($merchantEmail, $merchantId);
        $payee = $testee->payee();
        $this->assertEquals($merchantId, $payee->merchantId());
        $this->assertEquals($merchantEmail, $payee->email());
    }
}
