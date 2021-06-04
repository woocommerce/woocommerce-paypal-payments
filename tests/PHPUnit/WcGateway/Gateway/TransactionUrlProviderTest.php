<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Gateway;

use WooCommerce\PayPalCommerce\TestCase;

class TransactionUrlProviderTest extends TestCase
{
    /**
     * @dataProvider getTransactionUrlDataProvider
     */
    public function testGetTransactionUrlBase(
        string $sandboxUrl,
        string $liveUrl,
        string $orderPaymentMode,
        $expectedResult
    ) {
        $testee = new TransactionUrlProvider($sandboxUrl, $liveUrl);

        $wcOrder = \Mockery::mock(\WC_Order::class);

        $wcOrder->expects('get_meta')
            ->with(PayPalGateway::ORDER_PAYMENT_MODE_META_KEY, true)
            ->andReturn($orderPaymentMode);

        $this->assertSame($expectedResult, $testee->get_transaction_url_base($wcOrder));
    }

    function getTransactionUrlDataProvider(): array
    {
        $sandboxUrl = 'sandbox.example.com';
        $liveUrl = 'example.com';

        return [
            [
                $sandboxUrl, $liveUrl, 'sandbox', $sandboxUrl
            ],
            [
                $sandboxUrl, $liveUrl, 'live', $liveUrl
            ],
            [
                $sandboxUrl, $liveUrl, '', $liveUrl
            ]
        ];
    }
}
