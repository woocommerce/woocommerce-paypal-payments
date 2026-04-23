<?php

namespace WooCommerce\PayPalCommerce\Tests\Integration\Transaction;

use WC_Payment_Token;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PaymentTokensEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentSource;
use WooCommerce\PayPalCommerce\Tests\Integration\IntegrationMockedTestCase;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

/**
 * @group transactions
 */
class CreditcardTransactionTest extends IntegrationMockedTestCase
{
	public function setUp(): void
	{
		parent::setUp();

        $this->mockPaymentTokensEndpoint = \Mockery::mock(PaymentTokensEndpoint::class);
    }

	/**
	 * Sets up a test container with common mocks
	 *
	 * @param OrderEndpoint $orderEndpoint
	 * @param array $additionalServices Additional services to override
	 * @return ContainerInterface
	 */
	protected function setupTestContainer(OrderEndpoint $orderEndpoint, array $additionalServices = []): ContainerInterface
	{
		$services = [
			'api.endpoint.order' => function () use ($orderEndpoint) {
				return $orderEndpoint;
			},
		];

		return $this->bootstrapModule(array_merge($services, $additionalServices));
	}

	/**
	 * Creates a payment token and configures the mock endpoint to return it
	 *
	 * @param int $customer_id
	 * @param string $gateway_id
	 * @return WC_Payment_Token
	 */
	protected function setupPaymentToken(int $customer_id, string $gateway_id = PayPalGateway::ID): WC_Payment_Token
	{
		$paymentToken = $this->createAPaymentTokenForTheCustomer($customer_id, $gateway_id);

		$this->mockPaymentTokensEndpoint->shouldReceive('payment_tokens_for_customer')
			->andReturn([
				[
					'id' => $paymentToken->get_token(),
					'payment_source' => new PaymentSource(
						'card',
						(object)[
							'last_digits' => $paymentToken->get_last4(),
							'brand' => $paymentToken->get_card_type(),
							'expiry' => $paymentToken->get_expiry_year() . '-' . $paymentToken->get_expiry_month()
						]
					)
				]
			]);

		return $paymentToken;
	}

	/**
	 * Data provider for different product and discount combinations
	 */
	public function paymentProcessingDataProvider(): array
	{
		return [
			'simple product only' => [
				'products' => ['simple'],
				'discounts' => [],
				'expected_status' => 'processing'
			],
			'expensive product' => [
				'products' => ['simple_expensive'],
				'discounts' => [],
				'expected_status' => 'processing'
			],
			'multiple products' => [
				'products' => [
					['preset' => 'simple', 'quantity' => 2],
					'simple_expensive'
				],
				'discounts' => [],
				'expected_status' => 'processing'
			],//TODO fix the discount logic is failing due to taxes
			/*'simple product with percentage discount' => [
				'products' => ['simple'],
				'discounts' => ['percentage_10'],
				'expected_status' => 'processing'
			],
			'simple product with fixed discount' => [
				'products' => ['simple'],
				'discounts' => ['fixed_5'],
				'expected_status' => 'processing'
			],*/
		];
	}

	/**
	 * Tests credit card payment processing with different product combinations.
	 *
	 * GIVEN a WooCommerce order with various product and discount combinations
	 * AND valid PayPal order ID in POST data
	 * AND valid credit card form data
	 * WHEN the payment is processed through the credit card gateway
	 * THEN the payment should be successfully captured
	 * AND the order status should change to the expected status
	 * AND a transaction ID should be set on the order
	 *
	 * @dataProvider paymentProcessingDataProvider
	 */
	public function testProcessPayment(array $products, array $discounts, string $expected_status)
	{
		// Mock successful PayPal API response
		$mockOrderEndpoint = $this->mockOrderEndpoint('CAPTURE', false, true);
		$this->setupTestContainer($mockOrderEndpoint);

		// Create order with provided products and discounts
		$order = $this->getConfiguredOrder(
			$this->customer_id,
			'ppcp-credit-card-gateway',
			$products,
			$discounts,
			false
		);

		$paypal_order_id = 'TEST-PAYPAL-ORDER-' . uniqid();

		// Set the PayPal order ID in POST data (simulating frontend submission)
		$_POST['paypal_order_id'] = $paypal_order_id;
		$order->update_meta_data('_paypal_order_id', $paypal_order_id);
		$order->save();

		// Mock the session handler to return null (forcing fallback to POST/meta)
		$sessionHandler = \Mockery::mock(\WooCommerce\PayPalCommerce\Session\SessionHandler::class);
		$sessionHandler->shouldReceive('order')->andReturn(null);
		$sessionHandler->shouldReceive('destroy_session_data')->once();

		// Add session handler to container overrides
		$additionalServices = [
			'session.handler' => function() use ($sessionHandler) {
				return $sessionHandler;
			}
		];

		$c = $this->setupTestContainer($mockOrderEndpoint, $additionalServices);

		// Simulate credit card form data
		$_POST['ppcp-credit-card-gateway-card-number'] = '4111111111111111';
		$_POST['ppcp-credit-card-gateway-card-expiry'] = '12/25';
		$_POST['ppcp-credit-card-gateway-card-cvc'] = '123';

		// Get the gateway instance
		$gateway = $c->get('wcgateway.credit-card-gateway');

		// Process payment
		$result = $gateway->process_payment($order->get_id());

		// Assertions
		$this->assertEquals('success', $result['result']);
		$this->assertArrayHasKey('redirect', $result);

		// Verify order status changed
		$order = wc_get_order($order->get_id()); // Refresh order
		$this->assertEquals($expected_status, $order->get_status());
		$this->assertNotEmpty($order->get_transaction_id());

		// Clean up POST data
		unset($_POST['paypal_order_id']);
		unset($_POST['ppcp-credit-card-gateway-card-number']);
		unset($_POST['ppcp-credit-card-gateway-card-expiry']);
		unset($_POST['ppcp-credit-card-gateway-card-cvc']);
	}

	/**
	 * Tests payment processing with a saved/vaulted credit card.
	 *
	 * GIVEN a WooCommerce order
	 * AND a saved credit card token for the customer
	 * AND the saved card ID in POST data
	 * WHEN the payment is processed through the credit card gateway
	 * THEN the payment should be successfully captured using the vaulted card
	 * AND the order status should change to processing
	 * AND a transaction ID should be set on the order
	 */
	public function testProcessPaymentVaultedCard()
	{
		$mockOrderEndpoint = $this->mockOrderEndpoint('CAPTURE', false, true);
		$order = $this->getConfiguredOrder(
			$this->customer_id,
			'ppcp-credit-card-gateway',
			['simple'],
			[],
			false
		);
		$paypal_order_id = 'TEST-PAYPAL-ORDER-' . uniqid();

		$_POST['paypal_order_id'] = $paypal_order_id;
		$order->update_meta_data('_paypal_order_id', $paypal_order_id);
		$order->save();

		$paymentToken = $this->setupPaymentToken($this->customer_id, 'ppcp-credit-card-gateway');

		// Set the saved card in POST data (simulating frontend selection)
		$_POST['saved_credit_card'] = $paymentToken->get_token();

		$sessionHandler = \Mockery::mock(\WooCommerce\PayPalCommerce\Session\SessionHandler::class);
		$sessionHandler->shouldReceive('order')->andReturn(null);
		$sessionHandler->shouldReceive('destroy_session_data')->once();

		$additionalServices = [
			'session.handler' => function() use ($sessionHandler) {
				return $sessionHandler;
			},
			'api.endpoint.payment-tokens' => function() {
				return $this->mockPaymentTokensEndpoint;
			}
		];

		$c = $this->setupTestContainer($mockOrderEndpoint, $additionalServices);
		$gateway = $c->get('wcgateway.credit-card-gateway');

		$result = $gateway->process_payment($order->get_id());

		// Assertions
		$this->assertEquals('success', $result['result']);
		$this->assertArrayHasKey('redirect', $result);

		// Verify order status changed
		$order = wc_get_order($order->get_id()); // Refresh order
		$this->assertEquals('processing', $order->get_status());
		$this->assertNotEmpty($order->get_transaction_id());

		// Clean up POST data
		unset($_POST['saved_credit_card']);
	}
}
