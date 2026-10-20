<?php

namespace WooCommerce\PayPalCommerce\Api;

use Mockery;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Money;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Refund;
use WooCommerce\PayPalCommerce\ApiClient\Entity\RefundStatus;
use WooCommerce\PayPalCommerce\ApiClient\Entity\SellerPayableBreakdown;
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
		parent::setUp();

		when('get_comments')->justReturn([]);
		when('remove_filter')->justReturn(true);
		when('add_filter')->justReturn(true);

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
			->addMethods(['id', 'status', 'seller_payable_breakdown'])
			->getMock();
		$refund->method('id')->willReturn($refund_id);
		$refund->method('status')->willReturn(new RefundStatus(RefundStatus::COMPLETED));
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

	/**
	 * GIVEN a PayPal order with a dispute-hold refund that never refunded anything and a later COMPLETED refund
	 * WHEN the refund fees are updated
	 * THEN the stored totals only include the COMPLETED refund
	 * AND only the COMPLETED refund gets a "PayPal refund ID" order note
	 *
	 * @dataProvider unrefunded_status_provider
	 */
	public function testUnrefundedEntryIsLeftOutOfTheRefundFeeTotals(string $hold_status): void
	{
		$hold = $this->create_refund(
			'HOLD1',
			$hold_status,
			new Money(100.00, 'USD'),
			new Money(0.00, 'USD'),
			new Money(100.00, 'USD')
		);
		$completed_refund = $this->create_refund(
			'REAL1',
			RefundStatus::COMPLETED,
			new Money(30.00, 'USD'),
			new Money(1.20, 'USD'),
			new Money(28.80, 'USD')
		);

		[$stored, $notes] = $this->run_update([$hold, $completed_refund]);

		$this->assertTotal(30.0, 1.2, 28.8, $stored);
		$this->assertCount(1, $notes);
		$this->assertStringContainsString('REAL1', $notes[0]);
		$this->assertStringNotContainsString('HOLD1', implode(' ', $notes));
	}

	public function unrefunded_status_provider(): array
	{
		return [
			'failed dispute hold'    => [RefundStatus::FAILED],
			'cancelled dispute hold' => [RefundStatus::CANCELLED],
		];
	}

	private function build_paypal_order(array $refunds): Order
	{
		$payments = $this->getMockBuilder(\stdClass::class)
			->addMethods(['refunds'])
			->getMock();
		$payments->method('refunds')->willReturn($refunds);

		$purchase_unit = $this->getMockBuilder(\stdClass::class)
			->addMethods(['payments'])
			->getMock();
		$purchase_unit->method('payments')->willReturn($payments);

		$paypal_order = Mockery::mock(Order::class);
		$paypal_order->allows('purchase_units')->andReturn([$purchase_unit]);

		return $paypal_order;
	}

	private function run_update(array $refunds): array
	{
		$this->order_endpoint->method('order')->willReturn($this->build_paypal_order($refunds));

		$stored = null;
		$notes = [];
		$wc_order = Mockery::mock(WC_Order::class)->shouldIgnoreMissing();
		$wc_order->allows('get_meta')->with(PayPalGateway::ORDER_ID_META_KEY)->andReturn('test_order_id');
		$wc_order->allows('get_id')->andReturn(123);
		$wc_order->allows('update_meta_data')
			->with(PayPalGateway::REFUND_FEES_META_KEY, Mockery::on(static function ($meta) use (&$stored): bool {
				$stored = $meta;
				return true;
			}));
		$wc_order->allows('add_order_note')
			->andReturnUsing(static function ($note) use (&$notes): int {
				$notes[] = $note;
				return 1;
			});

		$this->refundFeesUpdater->update($wc_order);

		return [$stored, $notes];
	}

	private function assertTotal(float $gross, float $fee, float $net, $stored): void
	{
		$this->assertIsArray($stored);
		$this->assertEqualsWithDelta($gross, $stored['gross_amount']['value'], 0.001);
		$this->assertEqualsWithDelta($fee, $stored['paypal_fee']['value'], 0.001);
		$this->assertEqualsWithDelta($net, $stored['net_amount']['value'], 0.001);
		$this->assertSame('USD', $stored['gross_amount']['currency_code']);
		$this->assertSame('USD', $stored['paypal_fee']['currency_code']);
		$this->assertSame('USD', $stored['net_amount']['currency_code']);
	}

	private function create_refund(string $id, string $status, Money $gross, Money $fee, Money $net): Refund
	{
		$fee_in_receivable_currency = null;
		$net_in_receivable_currency = null;
		$total_refunded_amount = null;
		$platform_fees = [];

		$refund = Mockery::mock(Refund::class);
		$refund->allows('id')->andReturn($id);
		$refund->allows('status')->andReturn(new RefundStatus($status));
		$refund->allows('seller_payable_breakdown')->andReturn(
			new SellerPayableBreakdown(
				$gross,
				$fee,
				$fee_in_receivable_currency,
				$net,
				$net_in_receivable_currency,
				$total_refunded_amount,
				$platform_fees
			)
		);

		return $refund;
	}
}
