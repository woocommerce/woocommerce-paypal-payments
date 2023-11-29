<?php

namespace WooCommerce\PayPalCommerce\Api;

use Mockery;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Money;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ModularTestCase;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcGateway\Helper\RefundFeesUpdater;
use Psr\Log\LoggerInterface;
use WC_Order;
use function Brain\Monkey\Functions\when;

class OrderRefundFeesUpdateTest extends ModularTestCase
{

	private $order_endpoint;
	private $logger;
	private $refundFeesUpdater;

	public function setUp(): void
	{
		$this->order_endpoint = $this->createMock(OrderEndpoint::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->refundFeesUpdater = new RefundFeesUpdater($this->order_endpoint, $this->logger);
	}

	public function testUpdateWithoutPaypalOrderId(): void
	{
		$wc_order_id = 123;

		$wc_order = Mockery::mock(WC_Order::class);
		$wc_order->expects('get_meta')
			->with(PayPalGateway::ORDER_ID_META_KEY)
			->andReturn(null);

		$wc_order->expects('get_id')->andReturn($wc_order_id);

		$this->logger->expects($this->once())
			->method('error');

		$this->refundFeesUpdater->update($wc_order);
	}

	public function testUpdateWithValidData(): void
	{
		$wc_order_id = 123;
		$paypal_order_id = 'test_order_id';
		$refund_id = 'XYZ123';
		$meta_data = [
			'gross_amount' => ['value' => 10.0, 'currency_code' => 'USD'],
			'paypal_fee'   => ['value' => 7.0, 'currency_code' => 'USD'],
			'net_amount'   => ['value' => 3.0, 'currency_code' => 'USD'],
		];

		when('get_comments')->justReturn([]);

		$wc_order = Mockery::mock(WC_Order::class);
		$wc_order->expects('get_meta')
			->with(PayPalGateway::ORDER_ID_META_KEY)
			->andReturn($paypal_order_id);

		$wc_order->expects('get_id')
			->times(3)
			->andReturn($wc_order_id);

		$wc_order->expects('update_meta_data')
			->once()
			->with('_ppcp_paypal_refund_fees', $meta_data);

		$wc_order->expects('add_order_note')
			->once()
			->withArgs(function ($arg) use ($refund_id) {
				return strpos($arg, $refund_id) !== false;
			});

		$wc_order->expects('save')->once();

		$moneyGross = Mockery::mock(Money::class);
		$moneyGross->expects('value')->once()->andReturn($meta_data['gross_amount']['value']);
		$moneyGross->expects('currency_code')->once()->andReturn($meta_data['gross_amount']['currency_code']);

		$moneyFee = Mockery::mock(Money::class);
		$moneyFee->expects('value')->once()->andReturn($meta_data['paypal_fee']['value']);
		$moneyFee->expects('currency_code')->once()->andReturn($meta_data['paypal_fee']['currency_code']);

		$moneyNet = Mockery::mock(Money::class);
		$moneyNet->expects('value')->once()->andReturn($meta_data['net_amount']['value']);
		$moneyNet->expects('currency_code')->once()->andReturn($meta_data['net_amount']['currency_code']);

		$breakdown = $this->getMockBuilder(\stdClass::class)
			->addMethods(['gross_amount', 'paypal_fee', 'net_amount'])
			->getMock();
		$breakdown->method('gross_amount')->willReturn($moneyGross);
		$breakdown->method('paypal_fee')->willReturn($moneyFee);
		$breakdown->method('net_amount')->willReturn($moneyNet);

		$refund = $this->getMockBuilder(\stdClass::class)
			->addMethods(['id', 'seller_payable_breakdown'])
			->getMock();
		$refund->method('id')->willReturn($refund_id);
		$refund->method('seller_payable_breakdown')->willReturn($breakdown);

		$payments = $this->getMockBuilder(\stdClass::class)
			->addMethods(['refunds'])
			->getMock();
		$payments->method('refunds')->willReturn([$refund]);

		$purchase_unit = $this->getMockBuilder(\stdClass::class)
			->addMethods(['payments'])
			->getMock();
		$purchase_unit->method('payments')->willReturn($payments);

		$paypal_order = Mockery::mock(Order::class);
		$paypal_order->expects('purchase_units')->andReturn([$purchase_unit]);

		$this->order_endpoint->method('order')->with($paypal_order_id)->willReturn($paypal_order);

		$this->logger->expects($this->exactly(2))
			->method('debug')
			->withConsecutive(
				[$this->stringContains('Updating order paypal refund fees.')],
				[$this->stringContains('Updated order paypal refund fees.')]
			);

		$this->refundFeesUpdater->update($wc_order);
	}
}
