<?php

namespace WooCommerce\PayPalCommerce\Tests\Integration;

use WC_Payment_Token;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\Bearer;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\BillingAgreementsEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PaymentTokensEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentSource;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Token;
use WooCommerce\PayPalCommerce\Onboarding\State;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;

/**
 * @group subscriptions
 * @group subscription-vaulting
 * @group skip-ci
 */
class VaultingSubscriptionsTest extends IntegrationMockedTestCase
{
	public function setUp(): void
	{
		parent::setUp();

		// Common mock setup
        $this->mockPaymentTokensEndpoint = \Mockery::mock(PaymentTokensEndpoint::class);

        // Create customer and default product that can be reused
		$this->customer_id = $this->createCustomerIfNotExists();
		$this->default_product_id = $this->createAProductIfNotProvided();
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
			'api.endpoint.payment-tokens' => function () {
				return $this->mockPaymentTokensEndpoint;
			}
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
	 * Tests that vaulting is automatically enabled when subscription mode is set to vaulting_api.
	 *
	 * GIVEN a PayPal account with Reference Transactions enabled
	 * WHEN the subscription mode is set to "vaulting_api"
	 * THEN vaulting should be automatically enabled for the PayPal gateway
	 */
	public function test_vaulting_is_enabled_when_subscription_mode_is_vaulting_api()
	{
        $user_has_cap_callback = function ($allcaps, $caps, $args) {
            if (isset($args[0]) && $args[0] === 'manage_woocommerce') {
                $allcaps['manage_woocommerce'] = true;
            }
            return $allcaps;
        };
        add_filter('user_has_cap', $user_has_cap_callback, 10, 3);

        // Convert to Mockery mocks
        $billing_agreements_endpoint_mock = \Mockery::mock(BillingAgreementsEndpoint::class);
        $billing_agreements_endpoint_mock->shouldReceive('reference_transaction_enabled')
            ->andReturn(true);

        $state_mock = \Mockery::mock(State::class);
        $state_mock->shouldReceive('current_state')
            ->andReturn(State::STATE_ONBOARDED);

        $token_mock = \Mockery::mock(Token::class);
        $token_mock->shouldReceive('vaulting_available')
            ->andReturn(true);

        $bearer_mock = \Mockery::mock(Bearer::class);
        $bearer_mock->shouldReceive('bearer')
            ->andReturn($token_mock);

        // Create and configure the SettingsListener
        $c = $this->bootstrapModule([
            'api.endpoint.billing-agreements' => function () use ($billing_agreements_endpoint_mock) {
                return $billing_agreements_endpoint_mock;
            },
            'onboarding.state' => function () use ($state_mock) {
                return $state_mock;
            },
            'wcgateway.current-ppcp-settings-page-id' => function () {
                return '123';
            },
            'api.bearer' => function () use ($bearer_mock) {
                return $bearer_mock;
            },
        ]);

        $settings = $c->get('wcgateway.settings');

        // Store original settings to restore later
        $original_subscription_mode = $settings->get('subscriptions_mode');
        $original_vault_enabled = $settings->get('vault_enabled');

        try {
            $settings_listener = $c->get('wcgateway.settings.listener');
            $settings_listener->listen_for_vaulting_enabled();
            $_POST['ppcp'] = [
                'subscriptions_mode' => 'vaulting_api',
                'vault_enabled' => '0' // Explicitly set to disabled
            ];
            $_REQUEST['_wpnonce'] = wp_create_nonce('ppcp-settings');
            $settings_listener->listen_for_vaulting_enabled();

            // THEN vaulting should be automatically enabled for the PayPal gateway
            $this->assertTrue(
                get_option('woocommerce-ppcp-settings')['vault_enabled'],
                'Vaulting should be automatically enabled when subscription mode is set to vaulting_api'
            );

        } finally {
            unset($_POST['ppcp']);
            $settings->set('subscriptions_mode', $original_subscription_mode);
            $settings->set('vault_enabled', $original_vault_enabled);
            $settings->persist();
            remove_filter('user_has_cap', $user_has_cap_callback, 10);
        }

	}
	/**
	 * Data provider for payment gateway tests
	 */
	public function paymentGatewayProvider(): array
	{
		return [
			'PayPal Gateway' => [PayPalGateway::ID],
			'Credit Card Gateway' => [CreditCardGateway::ID]
		];
	}

	/**
	 * Tests PayPal renewal payment processing.
	 *
	 * GIVEN a subscription with a saved PayPal payment token due for renewal
	 * WHEN the renewal process is triggered
	 * THEN a new PayPal order should be created using the customer token
	 *
	 * @dataProvider paymentGatewayProvider
	 */
	public function test_renewal_payment_processing(string $gateway_id)
	{
		$mockOrderEndpoint = $this->mockOrderEndpoint('CAPTURE', true);
		$c = $this->setupTestContainer($mockOrderEndpoint);
        $this->setupPaymentToken($this->customer_id, $gateway_id);
		$subscription = $this->createSubscription($this->customer_id, $gateway_id);
		$renewal_order = $this->createRenewalOrder($this->customer_id, $gateway_id, $subscription->get_id());

		$renewal_handler = $c->get('wc-subscriptions.renewal-handler');
		$renewal_handler->renew($renewal_order);

		// Check that the order was processed
		$this->assertEquals('processing', $renewal_order->get_status(), 'The renewal order should be processing after successful payment');
		$this->assertNotEmpty($renewal_order->get_transaction_id(), 'The renewal order should have a transaction ID');
	}
	/**
	 * Tests that renewal processing handles failed payments correctly.
	 *
	 * GIVEN a subscription due for renewal
	 * WHEN the payment process fails with an exception
	 * THEN the renewal order should be marked as failed
	 */
	public function test_renewal_handles_failed_payment()
	{
		$mockOrderEndpoint = $this->mockOrderEndpoint('CAPTURE', false);
		$c = $this->setupTestContainer($mockOrderEndpoint);
        $this->setupPaymentToken($this->customer_id);
		$subscription = $this->createSubscription($this->customer_id, PayPalGateway::ID);
		$renewal_order = $this->createRenewalOrder($this->customer_id, PayPalGateway::ID, $subscription->get_id());
		$renewal_handler = $c->get('wc-subscriptions.renewal-handler');
		$renewal_handler->renew($renewal_order);

		// Check that the order status is failed
		$this->assertEquals('failed', $renewal_order->get_status(), 'The renewal order should be marked as failed when payment fails');
	}

	/**
	 * Tests authorization-only subscription renewals.
	 *
	 * GIVEN the payment intent is set to "AUTHORIZE"
	 * WHEN a subscription renewal payment is processed
	 * THEN the payment should be authorized but not captured
	 */
	public function test_authorize_only_subscription_renewal()
	{
		// Mock the OrderEndpoint with AUTHORIZE intent
		$mockOrderEndpoint = $this->mockOrderEndpoint('AUTHORIZE', true);
		$c = $this->setupTestContainer($mockOrderEndpoint);

		// Setup payment token and subscription
        $this->setupPaymentToken($this->customer_id);
		$subscription = $this->createSubscription($this->customer_id, PayPalGateway::ID);
		$renewal_order = $this->createRenewalOrder($this->customer_id, PayPalGateway::ID, $subscription->get_id());

		// Override the intent setting to ensure it's set to AUTHORIZE
		$settings = $c->get('wcgateway.settings');
		$original_intent = $settings->get('intent');
		$settings->set('intent', 'authorize');
		$settings->persist();

		try {
			// Process the renewal
			$renewal_handler = $c->get('wc-subscriptions.renewal-handler');
			$renewal_handler->renew($renewal_order);

			// Check that the order was processed with authorization
			$this->assertEquals('on-hold', $renewal_order->get_status(), 'The renewal order should be on-hold after successful authorization');
			$this->assertNotEmpty($renewal_order->get_transaction_id(), 'The renewal order should have a transaction ID');
			$this->assertEquals('AUTHORIZE', $mockOrderEndpoint->order('')->intent(), 'The order intent should be AUTHORIZE');
		} finally {
			// Restore original settings
			$settings->set('intent', $original_intent);
			$settings->persist();
		}
	}
}
