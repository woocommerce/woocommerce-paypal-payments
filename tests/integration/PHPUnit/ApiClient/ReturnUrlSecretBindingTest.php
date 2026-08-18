<?php
declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\Tests\Integration\ApiClient;

use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\OrderStatus;
use WooCommerce\PayPalCommerce\ApiClient\Factory\ExperienceContextBuilder;
use WooCommerce\PayPalCommerce\Tests\Integration\TestCase;

/**
 * @group transactions
 *
 * End-to-end proof that the real 'wcgateway.builder.experience-context' service and
 * ApiModule's 'woocommerce_paypal_payments_paypal_order_created' listener, wired via
 * the booted container, bind the single-use return-url secret to the PayPal order id
 * that PayPal actually created - and only when the builder issued a pending secret.
 */
class ReturnUrlSecretBindingTest extends TestCase {

	public function tearDown(): void {
		delete_transient( 'ppcp_ru_PP-TEST-BIND-1' );
		delete_transient( 'ppcp_ru_PP-TEST-BIND-2' );

		parent::tearDown();
	}

	/**
	 * GIVEN the real ExperienceContextBuilder built by the container, chained through
	 *       with_endpoint_return_urls() so it issues a pending return-url secret
	 * WHEN the PayPal order that the buyer's flow creates fires
	 *      'woocommerce_paypal_payments_paypal_order_created' with the new order id
	 * THEN the transient bound to that PayPal order id holds exactly the nonce that
	 *      was embedded in the return URL
	 */
	public function test_endpoint_return_url_nonce_is_bound_to_the_created_paypal_order(): void {
		// Arrange
		$builder = $this->getContainer()->get( 'wcgateway.builder.experience-context' );
		$this->assertInstanceOf( ExperienceContextBuilder::class, $builder );

		$experience_context = $builder->with_endpoint_return_urls()->build();
		$return_url         = $experience_context->to_array()['return_url'] ?? '';

		$query = array();
		parse_str( (string) wp_parse_url( $return_url, PHP_URL_QUERY ), $query );
		$nonce = $query['ppcp_return_nonce'] ?? '';
		$this->assertNotSame( '', $nonce, 'The endpoint return URL must carry a ppcp_return_nonce.' );

		// When
		do_action(
			'woocommerce_paypal_payments_paypal_order_created',
			new Order( 'PP-TEST-BIND-1', array(), new OrderStatus( OrderStatus::CREATED ) )
		);

		// Then
		$this->assertSame( $nonce, get_transient( 'ppcp_ru_PP-TEST-BIND-1' ) );
	}

	/**
	 * GIVEN the real ExperienceContextBuilder chained the way CreateOrderEndpoint
	 *       builds it for a custom return URL: with_default_paypal_config() (which
	 *       itself calls with_endpoint_return_urls() internally) followed by
	 *       with_custom_return_url()
	 * WHEN the PayPal order created that way fires
	 *      'woocommerce_paypal_payments_paypal_order_created' with the new order id
	 * THEN no secret is bound to that PayPal order id, because the custom return URL
	 *      retracted the pending secret that with_default_paypal_config() had issued
	 */
	public function test_custom_return_url_chain_binds_nothing(): void {
		// Arrange
		$builder = $this->getContainer()->get( 'wcgateway.builder.experience-context' );
		$this->assertInstanceOf( ExperienceContextBuilder::class, $builder );

		$builder
			->with_default_paypal_config()
			->with_custom_return_url( 'https://example.com/return' )
			->build();

		// When
		do_action(
			'woocommerce_paypal_payments_paypal_order_created',
			new Order( 'PP-TEST-BIND-2', array(), new OrderStatus( OrderStatus::CREATED ) )
		);

		// Then
		$this->assertFalse( get_transient( 'ppcp_ru_PP-TEST-BIND-2' ) );
	}

	/**
	 * GIVEN the plugin has finished booting, so ApiModule::run() registered its
	 *       'init' callback and 'init' has already fired
	 * WHEN the 'ppcp_return_url_binding_since' option is read
	 * THEN it holds a positive integer timestamp
	 */
	public function test_binding_since_option_is_written_on_boot(): void {
		$binding_since = get_option( 'ppcp_return_url_binding_since' );

		$this->assertIsNumeric( $binding_since );
		$this->assertGreaterThan( 0, (int) $binding_since );
	}
}
