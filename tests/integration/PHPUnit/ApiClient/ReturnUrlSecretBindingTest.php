<?php
declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\Tests\Integration\ApiClient;

use Closure;
use ReflectionClass;
use ReflectionFunction;
use WooCommerce\PayPalCommerce\ApiClient\ApiModule;
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

	/**
	 * What 'ppcp_return_url_binding_since' held once the plugin had booted, before
	 * any test in this class touched it.
	 *
	 * @var mixed
	 */
	private static $binding_since_at_boot;

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		self::$binding_since_at_boot = get_option( 'ppcp_return_url_binding_since', false );
	}

	public function tearDown(): void {
		delete_transient( 'ppcp_ru_PP-TEST-BIND-1' );
		delete_transient( 'ppcp_ru_PP-TEST-BIND-2' );
		delete_option( 'ppcp_return_url_binding_since' );

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
	 * GIVEN the plugin has finished booting on an installation whose version did not
	 *       change, so 'woocommerce_paypal_payments_gateway_migrate' never fired
	 *       during this run
	 * WHEN the value that 'ppcp_return_url_binding_since' held at boot is read
	 * THEN it is absent
	 *
	 * @scenario Booting must not stamp the option. A fresh install has no order in
	 *           transit to rescue, so a stamp there would only make ReturnUrlEndpoint
	 *           accept tokens that carry no bound secret for the width of the grace
	 *           window. The value is captured before the first test runs, so the
	 *           assertion cannot be satisfied by another test in this class having
	 *           deleted the option.
	 */
	public function test_boot_alone_does_not_write_the_binding_since_option(): void {
		$this->assertFalse( self::$binding_since_at_boot );
	}

	/**
	 * GIVEN an update from a version that created PayPal orders without a bound secret
	 * WHEN 'woocommerce_paypal_payments_gateway_migrate' runs with that version
	 * THEN 'ppcp_return_url_binding_since' is stamped
	 *
	 * @dataProvider versions_without_bound_secrets
	 */
	public function test_update_from_4_1_3_or_older_writes_the_binding_since_option( string $previous_version ): void {
		// When
		$this->migrate_from( $previous_version );

		// Then
		$this->assertGreaterThan( 0, (int) get_option( 'ppcp_return_url_binding_since', 0 ) );
	}

	public function versions_without_bound_secrets(): array {
		return array(
			'4.1.3' => array( '4.1.3' ),
			'4.1.2' => array( '4.1.2' ),
			'3.0.0' => array( '3.0.0' ),
		);
	}

	/**
	 * GIVEN a fresh install, or an update from a version that already binds secrets
	 * WHEN 'woocommerce_paypal_payments_gateway_migrate' runs with that version
	 * THEN 'ppcp_return_url_binding_since' stays absent
	 *
	 * @scenario Neither case can have an unbound order in transit, so a stamp would
	 *           only re-open the acceptance of unbound tokens.
	 *
	 * @dataProvider versions_with_nothing_to_rescue
	 *
	 * @param false|string $previous_version The previously installed plugin version.
	 */
	public function test_fresh_install_or_later_update_does_not_write_the_binding_since_option( $previous_version ): void {
		// When
		$this->migrate_from( $previous_version );

		// Then
		$this->assertFalse( get_option( 'ppcp_return_url_binding_since', false ) );
	}

	public function versions_with_nothing_to_rescue(): array {
		return array(
			'fresh install' => array( false ),
			'4.1.4'         => array( '4.1.4' ),
			'4.2.0'         => array( '4.2.0' ),
		);
	}

	/**
	 * GIVEN an installation that is updated from 4.1.3
	 * WHEN 'woocommerce_paypal_payments_gateway_migrate' runs, and runs a second time
	 * THEN the option holds the moment of the first run and does not move
	 *
	 * @scenario The option bounds a migration window. A later write would slide the
	 *           window forward and re-open the acceptance of unbound tokens.
	 */
	public function test_update_writes_the_binding_since_option_once(): void {
		// Arrange
		delete_option( 'ppcp_return_url_binding_since' );

		// When
		$this->migrate_from( '4.1.3' );
		$first = (int) get_option( 'ppcp_return_url_binding_since', 0 );

		$this->migrate_from( '4.1.3' );
		$second = (int) get_option( 'ppcp_return_url_binding_since', 0 );

		// Then
		$this->assertGreaterThan( 0, $first );
		$this->assertSame( $first, $second );
	}

	/**
	 * Runs only ApiModule's listener on the migrate action, since the other
	 * modules' migrations would rewrite settings in the shared test database.
	 *
	 * @param false|string $previous_version The previously installed plugin version.
	 */
	private function migrate_from( $previous_version ): void {
		global $wp_filter;

		$api_module_file = ( new ReflectionClass( ApiModule::class ) )->getFileName();
		$listeners       = array();

		foreach ( $wp_filter['woocommerce_paypal_payments_gateway_migrate']->callbacks as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$function = $callback['function'];

				if ( $function instanceof Closure && ( new ReflectionFunction( $function ) )->getFileName() === $api_module_file ) {
					$listeners[] = $function;
				}
			}
		}

		$this->assertCount( 1, $listeners, 'ApiModule must stamp the binding moment from the migrate action.' );

		$listeners[0]( $previous_version );
	}
}
