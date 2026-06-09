<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Gateway;

use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcGateway\Helper\Environment;

class TransactionUrlProviderTest extends TestCase
{
    /**
     * @dataProvider getTransactionUrlDataProvider
     */
    public function testGetTransactionUrlBase(
        string $sandboxUrl,
        string $liveUrl,
        string $orderPaymentMode,
        bool $isSandbox,
        $expectedResult
    ) {
        $testee = new TransactionUrlProvider($sandboxUrl, $liveUrl, new Environment($isSandbox));

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
            // Order payment mode takes precedence over the environment when present.
            [
                $sandboxUrl, $liveUrl, 'sandbox', false, $sandboxUrl
            ],
            [
                $sandboxUrl, $liveUrl, 'live', true, $liveUrl
            ],
            // No stored payment mode (e.g. local APMs): fall back to the environment.
            [
                $sandboxUrl, $liveUrl, '', true, $sandboxUrl
            ],
            [
                $sandboxUrl, $liveUrl, '', false, $liveUrl
            ],
        ];
    }
}
