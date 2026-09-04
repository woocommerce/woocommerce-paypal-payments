<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Tests\Integration\WcGateway\Processor;

use WC_Order;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\ExperienceContext;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\OrderStatus;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentSource;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
use WooCommerce\PayPalCommerce\Tests\Integration\IntegrationMockedTestCase;
use WooCommerce\PayPalCommerce\WcGateway\Exception\PayPalOrderMissingException;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcGateway\Processor\AuthorizedPaymentsProcessor;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;


class OrderProcessorTest extends IntegrationMockedTestCase
{
	protected function setupTestContainer(OrderEndpoint $orderEndpoint, array $additionalServices = []): ContainerInterface
	{
		$services = [
			'api.endpoint.order' => fn() => $orderEndpoint,
		];

		return $this->bootstrapModule(array_merge($services, $additionalServices));
	}

	public function testSuccessfulCapturePayment(): void
	{
		$mockOrderEndpoint = $this->mockOrderEndpoint('CAPTURE', false, true);
		$sessionHandler = \Mockery::mock(SessionHandler::class);
		$paypalOrder = $mockOrderEndpoint->order('test-order-id');
		$sessionHandler->shouldReceive('order')->andReturn($paypalOrder);

		$container = $this->setupTestContainer($mockOrderEndpoint, [
			'session.handler' => fn() => $sessionHandler,
		]);

		$wcOrder = $this->getConfiguredOrder(
			$this->customer_id,
			'ppcp-gateway',
			['simple'],
			[],
			false
		);

		$orderProcessor = $container->get('wcgateway.order-processor');
		$orderProcessor->process($wcOrder);

		$wcOrder = wc_get_order($wcOrder->get_id());
		$this->assertEquals('processing', $wcOrder->get_status());
		$this->assertNotEmpty($wcOrder->get_transaction_id());
		$this->assertStringStartsWith('TEST-CAPTURE-', $wcOrder->get_transaction_id());
		$this->assertEquals($paypalOrder->id(), $wcOrder->get_meta(PayPalGateway::ORDER_ID_META_KEY));
	}

	public function testSuccessfulAuthorization(): void
	{
		$mockOrderEndpoint = $this->mockOrderEndpoint('AUTHORIZE', false, true);
		$sessionHandler = \Mockery::mock(SessionHandler::class);
		$paypalOrder = $mockOrderEndpoint->order('test-order-id');
		$sessionHandler->shouldReceive('order')->andReturn($paypalOrder);

		$container = $this->setupTestContainer($mockOrderEndpoint, [
			'session.handler' => fn() => $sessionHandler,
		]);

		$wcOrder = $this->getConfiguredOrder(
			$this->customer_id,
			'ppcp-gateway',
			['simple'],
			[],
			false
		);

		$orderProcessor = $container->get('wcgateway.order-processor');
		$orderProcessor->process($wcOrder);

		$wcOrder = wc_get_order($wcOrder->get_id());
		$this->assertEquals('on-hold', $wcOrder->get_status());
		$this->assertNotEmpty($wcOrder->get_transaction_id());
		$this->assertStringStartsWith('TEST-AUTH-', $wcOrder->get_transaction_id());
		$this->assertEquals('false', $wcOrder->get_meta(AuthorizedPaymentsProcessor::CAPTURED_META_KEY));
	}

	public function testVirtualProductAutoCapture(): void
	{
		$virtualProduct = new \WC_Product_Simple();
		$virtualProduct->set_name('Test Virtual Product');
		$virtualProduct->set_regular_price('10.00');
		$virtualProduct->set_virtual(true);
		$virtualProduct->set_downloadable(true);
		$virtualProduct->set_status('publish');
		$virtualProduct->save();

		update_option( 'woocommerce-ppcp-data-settings', array( 'capture_virtual_orders' => true ) );

		$mockOrderEndpoint = $this->mockOrderEndpoint('AUTHORIZE', false, true);
		$sessionHandler = \Mockery::mock(SessionHandler::class);
		$paypalOrder = $mockOrderEndpoint->order('test-order-id');
		$sessionHandler->shouldReceive('order')->andReturn($paypalOrder);

		$mockAuthorizedPaymentsProcessor = \Mockery::mock(AuthorizedPaymentsProcessor::class);
		$mockAuthorizedPaymentsProcessor->shouldReceive('capture_authorized_payment')
			->once()
			->with(\Mockery::type(WC_Order::class));

		$container = $this->setupTestContainer($mockOrderEndpoint, [
			'session.handler' => fn() => $sessionHandler,
			'wcgateway.processor.authorized-payments' => fn() => $mockAuthorizedPaymentsProcessor,
		]);

		$wcOrder = wc_create_order();
		$wcOrder->set_customer_id($this->customer_id);
		$wcOrder->add_product($virtualProduct, 1);
		$wcOrder->set_payment_method('ppcp-gateway');
		$wcOrder->calculate_totals();
		$wcOrder->save();

		$orderProcessor = $container->get('wcgateway.order-processor');
		$orderProcessor->process($wcOrder);

		$wcOrder = wc_get_order($wcOrder->get_id());
		$this->assertEquals('on-hold', $wcOrder->get_status());
		$this->assertNotEmpty($wcOrder->get_transaction_id());

		wp_delete_post($virtualProduct->get_id(), true);
		delete_option( 'woocommerce-ppcp-data-settings' );
	}

	public function testPaymentDeclined(): void
	{
		$mockOrderEndpoint = $this->mockOrderEndpoint('CAPTURE', false, false);
		$sessionHandler = \Mockery::mock(SessionHandler::class);
		$paypalOrder = $mockOrderEndpoint->order('test-order-id');
		$sessionHandler->shouldReceive('order')->andReturn($paypalOrder);

		$container = $this->setupTestContainer($mockOrderEndpoint, [
			'session.handler' => fn() => $sessionHandler,
		]);

		$wcOrder = $this->getConfiguredOrder(
			$this->customer_id,
			'ppcp-gateway',
			['simple'],
			[],
			false
		);

		$orderProcessor = $container->get('wcgateway.order-processor');

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Payment provider declined the payment, please use a different payment method.');

		try {
			$orderProcessor->process($wcOrder);
		} finally {
			$wcOrder = wc_get_order($wcOrder->get_id());
			$this->assertEquals('failed', $wcOrder->get_status());
		}
	}

	public function testMissingPayPalOrder(): void
	{
		$_POST = [];
		$_GET = [];

		$mockOrderEndpoint = \Mockery::mock(OrderEndpoint::class);
		$mockOrderEndpoint->shouldReceive('order')
			->andThrow(new \Exception('Order ID should not exist - order() should not be called'));

		$sessionHandler = \Mockery::mock(SessionHandler::class);
		$sessionHandler->shouldReceive('order')->andReturn(null);

		$container = $this->setupTestContainer($mockOrderEndpoint, [
			'session.handler' => fn() => $sessionHandler,
		]);

		$wcOrder = $this->getConfiguredOrder(
			$this->customer_id,
			'ppcp-gateway',
			['simple'],
			[],
			false
		);

		$wcOrder->delete_meta_data('_ppcp_paypal_order_id');
		$wcOrder->save();

		$wcOrder = wc_get_order($wcOrder->get_id());

		$orderProcessor = $container->get('wcgateway.order-processor');

		$this->expectException(PayPalOrderMissingException::class);
		$this->expectExceptionMessage('There was an error processing your order.');

		$orderProcessor->process($wcOrder);
	}

	public function testAlreadyCompletedOrder(): void
	{
		$mockOrderEndpoint = \Mockery::mock(OrderEndpoint::class);
		$paypalOrder = \Mockery::mock(Order::class)->shouldIgnoreMissing();
		$paypalOrder->shouldReceive('id')->andReturn('TEST-COMPLETED-ORDER');

		$orderStatus = \Mockery::mock(OrderStatus::class);
		$orderStatus->shouldReceive('is')->with(OrderStatus::COMPLETED)->andReturn(true);
		$orderStatus->shouldReceive('name')->andReturn('COMPLETED');
		$paypalOrder->shouldReceive('status')->andReturn($orderStatus);

		$mockOrderEndpoint->shouldReceive('order')
			->with('TEST-COMPLETED-ORDER')
			->andReturn($paypalOrder);

		$sessionHandler = \Mockery::mock(SessionHandler::class);
		$sessionHandler->shouldReceive('order')->andReturn($paypalOrder);

		$container = $this->setupTestContainer($mockOrderEndpoint, [
			'session.handler' => fn() => $sessionHandler,
		]);

		$wcOrder = $this->getConfiguredOrder(
			$this->customer_id,
			'ppcp-gateway',
			['simple'],
			[],
			false
		);

		$originalStatus = $wcOrder->get_status();

		$orderProcessor = $container->get('wcgateway.order-processor');
		$orderProcessor->process($wcOrder);

		$wcOrder = wc_get_order($wcOrder->get_id());
		$this->assertEquals($originalStatus, $wcOrder->get_status());
	}

	/**
	 * GIVEN a buyer paying with the card funding source
	 * WHEN a PayPal order is created for their WooCommerce order
	 * THEN the experience context forces the guest checkout landing page, regardless of the merchant's setting
	 */
	public function testCreateOrderForCardFundingSourceForcesGuestCheckoutLandingPage(): void
	{
		$capturedPaymentSource = $this->captureCreatedPaymentSource('card');

		$this->assertSame(
			ExperienceContext::LANDING_PAGE_GUEST_CHECKOUT,
			$capturedPaymentSource->properties()->experience_context['landing_page']
		);
	}

	/**
	 * GIVEN a buyer paying with the default PayPal funding source
	 * WHEN a PayPal order is created for their WooCommerce order
	 * THEN the experience context uses the merchant's configured landing page instead of being forced
	 */
	public function testCreateOrderForDefaultFundingSourceUsesMerchantLandingPageSetting(): void
	{
		$capturedPaymentSource = $this->captureCreatedPaymentSource('paypal');

		$this->assertSame(
			ExperienceContext::LANDING_PAGE_LOGIN,
			$capturedPaymentSource->properties()->experience_context['landing_page']
		);
	}

	/**
	 * Creates a PayPal order via OrderProcessor::create_order() for the given funding source
	 * and returns the PaymentSource that was passed to OrderEndpoint::create().
	 */
	private function captureCreatedPaymentSource(string $fundingSource): PaymentSource
	{
		$capturedPaymentSource = null;

		$mockOrderEndpoint = \Mockery::mock(OrderEndpoint::class);
		$mockOrderEndpoint->shouldReceive('create')
			->andReturnUsing(function (...$args) use (&$capturedPaymentSource) {
				$capturedPaymentSource = $args[5];
				return \Mockery::mock(Order::class)->shouldIgnoreMissing();
			});

		$settingsProvider = \Mockery::mock(SettingsProvider::class)->shouldIgnoreMissing();
		$settingsProvider->shouldReceive('landing_page_enum')->andReturn(ExperienceContext::LANDING_PAGE_LOGIN);

		$container = $this->setupTestContainer($mockOrderEndpoint, [
			'settings.settings-provider' => fn() => $settingsProvider,
		]);

		$wcOrder = $this->getConfiguredOrder(
			$this->customer_id,
			'ppcp-gateway',
			['simple'],
			[],
			false
		);

		$orderProcessor = $container->get('wcgateway.order-processor');
		$orderProcessor->create_order($wcOrder, $fundingSource);

		return $capturedPaymentSource;
	}
}
