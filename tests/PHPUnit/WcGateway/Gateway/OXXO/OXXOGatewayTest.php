<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Gateway\OXXO;

use Mockery;
use Psr\Log\LoggerInterface;
use WC_Order;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PurchaseUnit;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PurchaseUnitFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\ShippingPreferenceFactory;
use WooCommerce\PayPalCommerce\Onboarding\Environment;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\TransactionUrlProvider;
use function Brain\Monkey\Functions\when;

class OXXOGatewayTest extends TestCase
{
private $orderEndpoint;
private $purchaseUnitFactory;
private $shippingPreferenceFactory;
private $environment;
private $logger;
private $wcOrder;
private $transactionUrlProvider;
private $testee;

	public function setUp(): void
	{
		parent::setUp();

		$this->orderEndpoint = Mockery::mock(OrderEndpoint::class);
		$this->purchaseUnitFactory = Mockery::mock(PurchaseUnitFactory::class);
		$this->shippingPreferenceFactory = Mockery::mock(ShippingPreferenceFactory::class);
		$this->transactionUrlProvider = Mockery::mock(TransactionUrlProvider::class);
		$this->environment = Mockery::mock(Environment::class);
		$this->logger = Mockery::mock(LoggerInterface::class);

		$this->wcOrder = Mockery::mock(WC_Order::class);
		when('wc_get_order')->justReturn($this->wcOrder);
		when('get_option')->justReturn([
			'title' => 'foo',
			'description' => 'bar',
		]);

		$this->testee = new OXXOGateway(
			$this->orderEndpoint,
			$this->purchaseUnitFactory,
			$this->shippingPreferenceFactory,
			'oxxo.svg',
			$this->transactionUrlProvider,
			$this->environment,
			$this->logger
		);
	}

	public function testProcessPaymentSuccess()
	{
		$this->wcOrder->shouldReceive('get_billing_first_name')->andReturn('John');
		$this->wcOrder->shouldReceive('get_billing_last_name')->andReturn('Doe');
		$this->wcOrder->shouldReceive('get_billing_email')->andReturn('foo@bar.com');
		$this->wcOrder->shouldReceive('get_billing_country')->andReturn('MX');

		list($purchaseUnit, $shippingPreference) = $this->setStubs();

		$linkHref = 'https://sandbox.paypal.com/payment/oxxo?token=ABC123';
		$this->orderEndpoint
			->shouldReceive('confirm_payment_source')
			->with('1', [
					'oxxo' => [
						'name' => 'John Doe',
						'email' => 'foo@bar.com',
						'country_code' => 'MX',
					]
				]
			)->andReturn((object)[
				'links' => [
					(object)[
						'rel' => 'payer-action',
						'href' => $linkHref,
					],
				]
			]);

		$order = Mockery::mock(Order::class);
		$order->shouldReceive('id')->andReturn('1');
		$order->shouldReceive('intent');
		$order->shouldReceive('payment_source');

		$this->orderEndpoint
			->shouldReceive('create')
			->with([$purchaseUnit], $shippingPreference)
			->andReturn($order);

		$this->wcOrder
			->shouldReceive('add_meta_data')
			->with('ppcp_oxxo_payer_action', $linkHref)
			->andReturn(true);
		$this->wcOrder->shouldReceive('save_meta_data');
		$this->wcOrder->shouldReceive('update_meta_data');
		$this->wcOrder->shouldReceive('save');

		$this->environment->shouldReceive('current_environment_is');

		$woocommerce = Mockery::mock(\WooCommerce::class);
		$cart = Mockery::mock(\WC_Cart::class);
		when('WC')->justReturn($woocommerce);
		$woocommerce->cart = $cart;
		$cart->shouldReceive('empty_cart');

		$result = $this->testee->process_payment(1);
		$this->assertEquals('success', $result['result']);
	}

	public function testProcessPaymentFailure()
	{
		list($purchaseUnit, $shippingPreference) = $this->setStubs();

		$this->orderEndpoint
			->shouldReceive('create')
			->with([$purchaseUnit], $shippingPreference)
			->andThrows(RuntimeException::class);

		$this->logger->shouldReceive('error');
		when('wc_add_notice')->justReturn();
		when('wc_get_checkout_url')->justReturn();
		$this->wcOrder->shouldReceive('update_status');

		$result = $this->testee->process_payment(1);
		$this->assertEquals('failure', $result['result']);

	}

	/**
	 * @return array
	 */
	private function setStubs(): array
	{
		$purchaseUnit = Mockery::mock(PurchaseUnit::class);
		$this->purchaseUnitFactory
			->shouldReceive('from_wc_order')
			->with($this->wcOrder)
			->andReturn($purchaseUnit);

		$shippingPreference = 'SOME_SHIPPING_PREFERENCE';
		$this->shippingPreferenceFactory
			->shouldReceive('from_state')
			->with($purchaseUnit, 'checkout')
			->andReturn($shippingPreference);
		return array($purchaseUnit, $shippingPreference);
	}
}
