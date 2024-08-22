<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Gateway;

use Mockery;
use WC_Order;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcGateway\Admin\FeesRenderer;
use function Brain\Monkey\Functions\when;

class FeesRendererTest extends TestCase
{
	private $renderer;

	public function setUp(): void
	{
		parent::setUp();

		$this->renderer = new FeesRenderer();

		when('wc_help_tip')->returnArg();
		when('wc_price')->returnArg();
	}

	public function testRender() {
		$wcOrder = Mockery::mock(WC_Order::class);

		$wcOrder->expects('get_meta')
			->with(PayPalGateway::FEES_META_KEY)
			->andReturn([
				'gross_amount' => [
					'currency_code' => 'USD',
					'value' => '10.42',
				],
				'paypal_fee' => [
					'currency_code' => 'USD',
					'value' => '0.41',
				],
				'net_amount' => [
					'currency_code' => 'USD',
					'value' => '10.01',
				],
			]);

		$wcOrder->expects('get_meta')
			->with(PayPalGateway::REFUND_FEES_META_KEY)
			->andReturn([
				'gross_amount' => [
					'currency_code' => 'USD',
					'value' => '20.52',
				],
				'paypal_fee' => [
					'currency_code' => 'USD',
					'value' => '0.51',
				],
				'net_amount' => [
					'currency_code' => 'USD',
					'value' => '50.01',
				],
			]);

		$result = $this->renderer->render($wcOrder);
		$this->assertStringContainsString('Fee', $result);
		$this->assertStringContainsString('0.41', $result);
		$this->assertStringContainsString('Payout', $result);
		$this->assertStringContainsString('10.01', $result);
		$this->assertStringContainsString('PayPal Refund Fee', $result);
		$this->assertStringContainsString('0.51', $result);
		$this->assertStringContainsString('PayPal Refund', $result);
		$this->assertStringContainsString('50.01', $result);
	}

	public function testRenderWithoutNet() {
		$wcOrder = Mockery::mock(WC_Order::class);

		$wcOrder->expects('get_meta')
			->with(PayPalGateway::FEES_META_KEY)
			->andReturn([
				'paypal_fee' => [
					'currency_code' => 'USD',
					'value' => '0.41',
				],
			]);

		$wcOrder->expects('get_meta')
			->with(PayPalGateway::REFUND_FEES_META_KEY)
			->andReturn([]);

		$result = $this->renderer->render($wcOrder);
		$this->assertStringContainsString('Fee', $result);
		$this->assertStringContainsString('0.41', $result);
		$this->assertStringNotContainsString('Payout', $result);
	}

	/**
     * @dataProvider noFeesDataProvider
     */
    public function testNoFees($meta) {
        $wcOrder = Mockery::mock(WC_Order::class);

        $wcOrder->expects('get_meta')
            ->with(PayPalGateway::FEES_META_KEY)
            ->andReturn($meta);

		$wcOrder->expects('get_meta')
			->with(PayPalGateway::REFUND_FEES_META_KEY)
			->andReturn([]);

        $this->assertSame('', $this->renderer->render($wcOrder));
    }

    function noFeesDataProvider(): array
    {
        return [
			['hello'],
            [[]],
            [['paypal_fee' => 'hello']],
        ];
    }
}
