<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Entity;

use Inpsyde\PayPalCommerce\ApiClient\TestCase;

class MoneyTest extends TestCase
{

    public function test()
    {
        $testee = new Money(1.10, 'currencyCode');
        $this->assertEquals(1.10, $testee->value());
        $this->assertEquals('currencyCode', $testee->currency_code());

        $expected = [
            'currency_code' => 'currencyCode',
            'value' => 1.10,
        ];
        $this->assertEquals($expected, $testee->to_array());
    }
}
