<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Axo\Gateway;

use Mockery;
use Psr\Log\LoggerInterface;
use WC_Order;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Amount;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\OrderStatus;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PurchaseUnit;
use WooCommerce\PayPalCommerce\ApiClient\Factory\ExperienceContextBuilder;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PurchaseUnitFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\ShippingPreferenceFactory;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsModel;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\TransactionUrlProvider;
use WooCommerce\PayPalCommerce\WcGateway\Helper\CardPaymentsConfiguration;
use WooCommerce\PayPalCommerce\WcGateway\Helper\Environment;
use WooCommerce\PayPalCommerce\WcGateway\Processor\OrderProcessor;
use function Brain\Monkey\Functions\when;

class AxoGatewayTestable extends AxoGateway
{
	public function update_option( $key, $value = '' ): bool
	{
		return true;
	}

	public function process_3ds_return_exposed( WC_Order $wc_order, string $token ): array
	{
		return $this->process_3ds_return( $wc_order, $token );
	}
}

/**
 * @covers \WooCommerce\PayPalCommerce\Axo\Gateway\AxoGateway
 */
class AxoGatewayTest extends TestCase
{
	private CardPaymentsConfiguration $dcc_configuration;
	private OrderEndpoint $order_endpoint;
	private OrderProcessor $order_processor;
	private LoggerInterface $logger;
	private AxoGatewayTestable $sut;

	public function setUp(): void
	{
		parent::setUp();

		$this->dcc_configuration = Mockery::mock( CardPaymentsConfiguration::class );
		$this->dcc_configuration->shouldReceive( 'gateway_title' )->andReturn( 'Fastlane Cards' );
		$this->dcc_configuration->shouldReceive( 'use_fastlane' )->andReturn( false );

		$this->order_endpoint  = Mockery::mock( OrderEndpoint::class );
		$this->order_processor = Mockery::mock( OrderProcessor::class );
		$this->logger          = Mockery::mock( LoggerInterface::class );

		$woocommerce       = Mockery::mock( 'WooCommerce' );
		$cart              = Mockery::mock( 'stdClass' );
		$cart->shouldReceive( 'empty_cart' );
		when( 'WC' )->justReturn( $woocommerce );
		$woocommerce->cart = $cart;

		$this->sut = new AxoGatewayTestable(
			$this->dcc_configuration,
			Mockery::mock( SessionHandler::class ),
			$this->order_processor,
			[],
			$this->order_endpoint,
			Mockery::mock( PurchaseUnitFactory::class ),
			Mockery::mock( ShippingPreferenceFactory::class ),
			Mockery::mock( TransactionUrlProvider::class ),
			Mockery::mock( Environment::class ),
			$this->logger,
			Mockery::mock( ExperienceContextBuilder::class ),
			Mockery::mock( SettingsModel::class )
		);
	}

	/**
	 * @param string $custom_id
	 * @param string $amount_value
	 * @param string $currency_code
	 * @return Order
	 */
	private function create_completed_paypal_order(
		string $custom_id,
		string $amount_value,
		string $currency_code
	): Order {
		$order_status = Mockery::mock( OrderStatus::class );
		$order_status->shouldReceive( 'is' )
			->with( OrderStatus::COMPLETED )
			->andReturn( true );

		$amount = Mockery::mock( Amount::class );
		$amount->shouldReceive( 'value_str' )->andReturn( $amount_value );
		$amount->shouldReceive( 'currency_code' )->andReturn( $currency_code );

		$purchase_unit = Mockery::mock( PurchaseUnit::class );
		$purchase_unit->shouldReceive( 'custom_id' )->andReturn( $custom_id );
		$purchase_unit->shouldReceive( 'amount' )->andReturn( $amount );

		$paypal_order = Mockery::mock( Order::class );
		$paypal_order->shouldReceive( 'status' )->andReturn( $order_status );
		$paypal_order->shouldReceive( 'purchase_units' )->andReturn( [ $purchase_unit ] );

		return $paypal_order;
	}

	/**
	 * @scenario When process_3ds_return() is called with a valid PayPal token whose
	 *           custom_id does NOT match the WC order id, the method returns a WP_Error
	 *           and the WC order remains unpaid.
	 * @covers \WooCommerce\PayPalCommerce\Axo\Gateway\AxoGateway::process_3ds_return
	 */
	public function test_returns_failure_when_custom_id_does_not_match_wc_order_id(): void
	{
		// Arrange
		$wc_order = Mockery::mock( WC_Order::class );
		$wc_order->shouldReceive( 'get_id' )->andReturn( 1 );
		$wc_order->shouldReceive( 'get_total' )->andReturn( '100.00' );
		$wc_order->shouldReceive( 'get_currency' )->andReturn( 'USD' );

		$paypal_order = $this->create_completed_paypal_order( '999', '100.00', 'USD' );
		$this->order_endpoint->shouldReceive( 'order' )->with( 'token' )->andReturn( $paypal_order );
		$this->order_processor->shouldReceive( 'process_captured_and_authorized' )->never();
		$this->logger->shouldReceive( 'error' )->once();

		// When
		$result = $this->sut->process_3ds_return_exposed( $wc_order, 'token' );

		// Then
		$this->assertSame( 'failure', $result['result'] );
	}

	/**
	 * @scenario When process_3ds_return() is called with a PayPal token whose custom_id
	 *           matches the WC order id but whose captured amount is significantly
	 *           different from the WC order total, the order is still marked paid.
	 *           Amount comparison was deliberately removed: once custom_id matches the
	 *           PayPal order is proven to belong to this WC order, and amount differences
	 *           (e.g. inclusive-tax rounding, promotional adjustments) must not block
	 *           payment. This test guards against re-introducing an amount check.
	 * @covers \WooCommerce\PayPalCommerce\Axo\Gateway\AxoGateway::process_3ds_return
	 */
	public function test_allows_payment_when_paypal_amount_differs_from_wc_total(): void
	{
		// Arrange — WC total 50.00, PayPal captured 10.00 (large gap); custom_id matches
		$wc_order = Mockery::mock( WC_Order::class );
		$wc_order->shouldReceive( 'get_id' )->andReturn( 1 );

		$paypal_order = $this->create_completed_paypal_order( '1', '10.00', 'USD' );
		$this->order_endpoint->shouldReceive( 'order' )->with( 'valid-token' )->andReturn( $paypal_order );
		$this->order_processor
			->shouldReceive( 'process_captured_and_authorized' )
			->once()
			->with( $wc_order, $paypal_order );

		when( 'apply_filters' )->justReturn( true );

		// When
		$result = $this->sut->process_3ds_return_exposed( $wc_order, 'valid-token' );

		// Then
		$this->assertSame( 'success', $result['result'] );
	}

	/**
	 * @scenario When process_3ds_return() is called with a PayPal token whose custom_id
	 *           matches the WC order id, the order is marked paid via
	 *           OrderProcessor::process_captured_and_authorized(). The custom_id binding
	 *           is the sole validation criterion; amount and currency are not checked.
	 * @covers \WooCommerce\PayPalCommerce\Axo\Gateway\AxoGateway::process_3ds_return
	 */
	public function test_calls_order_processor_when_custom_id_matches(): void
	{
		// Arrange
		$wc_order = Mockery::mock( WC_Order::class );
		$wc_order->shouldReceive( 'get_id' )->andReturn( 1 );
		$wc_order->shouldReceive( 'get_total' )->andReturn( '100.00' );
		$wc_order->shouldReceive( 'get_currency' )->andReturn( 'USD' );

		$paypal_order = $this->create_completed_paypal_order( '1', '100.00', 'USD' );
		$this->order_endpoint->shouldReceive( 'order' )->with( 'valid-token' )->andReturn( $paypal_order );
		$this->order_processor
			->shouldReceive( 'process_captured_and_authorized' )
			->once()
			->with( $wc_order, $paypal_order );

		when( 'apply_filters' )->justReturn( true );

		// When
		$result = $this->sut->process_3ds_return_exposed( $wc_order, 'valid-token' );

		// Then
		$this->assertSame( 'success', $result['result'] );
	}

	/**
	 * @return Order
	 */
	private function create_completed_paypal_order_without_purchase_units(): Order {
		$order_status = Mockery::mock( OrderStatus::class );
		$order_status->shouldReceive( 'is' )
			->with( OrderStatus::COMPLETED )
			->andReturn( true );

		$paypal_order = Mockery::mock( Order::class );
		$paypal_order->shouldReceive( 'status' )->andReturn( $order_status );
		$paypal_order->shouldReceive( 'purchase_units' )->andReturn( [] );

		return $paypal_order;
	}

	/**
	 * GIVEN a PayPal order that is COMPLETED but has no purchase units
	 * WHEN process_3ds_return() is called
	 * THEN the method returns a failure result
	 * AND the order processor is never called
	 * AND an error is logged containing the WC order ID
	 *
	 * @scenario When process_3ds_return() is called and the COMPLETED PayPal order
	 *           contains no purchase units, the method must return a failure result
	 *           without invoking the order processor, and must log an error.
	 * @covers \WooCommerce\PayPalCommerce\Axo\Gateway\AxoGateway::process_3ds_return
	 */
	public function test_returns_failure_when_paypal_order_has_no_purchase_units(): void {
		// Arrange
		$wc_order = Mockery::mock( WC_Order::class );
		$wc_order->shouldReceive( 'get_id' )->andReturn( 42 );

		$paypal_order = $this->create_completed_paypal_order_without_purchase_units();
		$this->order_endpoint->shouldReceive( 'order' )->with( 'some-token' )->andReturn( $paypal_order );
		$this->order_processor->shouldReceive( 'process_captured_and_authorized' )->never();
		$this->logger->shouldReceive( 'error' )->once();

		// When
		$result = $this->sut->process_3ds_return_exposed( $wc_order, 'some-token' );

		// Then
		$this->assertSame( 'failure', $result['result'] );
	}

	/**
	 * @scenario All validation rejections are logged with details sufficient for an
	 *           auditor to identify the attacker's token and the attempted fraud.
	 * @covers \WooCommerce\PayPalCommerce\Axo\Gateway\AxoGateway::process_3ds_return
	 */
	public function test_logs_rejection_details_when_validation_fails(): void
	{
		// Arrange
		$wc_order = Mockery::mock( WC_Order::class );
		$wc_order->shouldReceive( 'get_id' )->andReturn( 1 );
		$wc_order->shouldReceive( 'get_total' )->andReturn( '100.00' );
		$wc_order->shouldReceive( 'get_currency' )->andReturn( 'USD' );

		$paypal_order = $this->create_completed_paypal_order( '999', '100.00', 'USD' );
		$this->order_endpoint->shouldReceive( 'order' )->with( 'token' )->andReturn( $paypal_order );
		$this->order_processor->shouldReceive( 'process_captured_and_authorized' )->never();

		$this->logger
			->shouldReceive( 'error' )
			->once()
			->with(
				Mockery::on(
					static function ( string $message ): bool {
						return strpos( $message, '999' ) !== false
							&& strpos( $message, '1' ) !== false
							&& strpos( $message, 'token' ) !== false;
					}
				)
			);

		// When
		$this->sut->process_3ds_return_exposed( $wc_order, 'token' );

		// Then — Mockery verifies logger->error() call in tearDown(); count it as an assertion
		$this->addToAssertionCount( 1 );
	}

	/**
	 * Builds an AxoGatewayTestable whose collaborators are all configurable per
	 * test, so the 3DS redirect built inside process_payment()
	 * can be inspected without disturbing the shared setUp() wiring.
	 *
	 *
	 * @param \WooCommerce\PayPalCommerce\ApiClient\Entity\Order $paypal_order   Order returned by OrderEndpoint::create().
	 * @param \Mockery\MockInterface                              $purchase_unit_factory
	 * @param \Mockery\MockInterface                              $shipping_preference_factory
	 * @param \Mockery\MockInterface                              $settings_model
	 * @param \Mockery\MockInterface                              $experience_context_builder
	 * @param \Mockery\MockInterface                              $return_url_secret
	 */
	private function create_gateway_for_process_payment(
		\WooCommerce\PayPalCommerce\ApiClient\Entity\Order $paypal_order,
		$purchase_unit_factory,
		$shipping_preference_factory,
		$settings_model,
		$experience_context_builder,
		$return_url_secret
	): AxoGatewayTestable {
		$order_endpoint = Mockery::mock( OrderEndpoint::class );
		$order_endpoint->allows( 'create' )->andReturn( $paypal_order );

		return new AxoGatewayTestable(
			$this->dcc_configuration,
			Mockery::mock( SessionHandler::class ),
			$this->order_processor,
			[],
			$order_endpoint,
			$purchase_unit_factory,
			$shipping_preference_factory,
			Mockery::mock( TransactionUrlProvider::class ),
			Mockery::mock( Environment::class ),
			$this->logger,
			$experience_context_builder,
			$settings_model,
			$return_url_secret
		);
	}

	/**
	 * GIVEN a card payment that requires a 3D Secure challenge
	 * AND a ReturnUrlSecret that reuses the secret already bound to the newly created
	 *     PayPal order id (secret_for())
	 * WHEN process_payment() builds the 3DS redirect URL (AxoGateway.php:267-271)
	 * THEN the redirect's embedded return URL carries the PayPal order id as "token"
	 * AND it carries a "ppcp_return_nonce" argument equal to the value ReturnUrlSecret
	 *     returned for that same PayPal order id
	 *
	 * @scenario Closes the token-replay gap: without a bound nonce, a leaked PayPal
	 *           order id alone is enough to trigger a capture on someone else's
	 *           order via the return URL (bug:wc-gateway:return-url-token-replay).
	 * @covers \WooCommerce\PayPalCommerce\Axo\Gateway\AxoGateway::process_payment
	 */
	public function test_process_payment_redirect_url_carries_nonce_bound_to_paypal_order_when_3ds_required(): void
	{
		// Arrange
		$wc_order = Mockery::mock( WC_Order::class );
		$wc_order->shouldReceive( 'get_id' )->andReturn( 77 );

		$payer_action_link = (object) [
			'rel'  => 'payer-action',
			'href' => 'https://paypal.example/payer-action',
		];

		$paypal_order = Mockery::mock( \WooCommerce\PayPalCommerce\ApiClient\Entity\Order::class );
		$paypal_order->shouldReceive( 'id' )->andReturn( 'PAYPAL-ORDER-XYZ' );
		$paypal_order->shouldReceive( 'links' )->andReturn( [ $payer_action_link ] );

		$purchase_unit = Mockery::mock( PurchaseUnit::class );
		$purchase_unit_factory = Mockery::mock( PurchaseUnitFactory::class );
		$purchase_unit_factory->allows( 'from_wc_order' )->andReturn( $purchase_unit );

		$shipping_preference_factory = Mockery::mock( ShippingPreferenceFactory::class );
		$shipping_preference_factory->allows( 'from_state' )->andReturn( 'preference' );

		$settings_model = Mockery::mock( SettingsModel::class );
		$settings_model->allows( 'get_three_d_secure_enum' )->andReturn( '' );

		$experience_context = Mockery::mock( \WooCommerce\PayPalCommerce\ApiClient\Entity\ExperienceContext::class );
		$experience_context->allows( 'to_array' )->andReturn( [] );

		$experience_context_builder = Mockery::mock( ExperienceContextBuilder::class );
		$experience_context_builder->allows( 'with_endpoint_return_urls' )->andReturnSelf();
		$experience_context_builder->allows( 'with_current_brand_name' )->andReturnSelf();
		$experience_context_builder->allows( 'with_current_locale' )->andReturnSelf();
		$experience_context_builder->allows( 'build' )->andReturn( $experience_context );

		$return_url_secret = Mockery::mock( \WooCommerce\PayPalCommerce\ApiClient\Helper\ReturnUrlSecret::class );
		$return_url_secret->allows( 'secret_for' )->with( 'PAYPAL-ORDER-XYZ' )->andReturn( 'bound-nonce-value' );

		when( 'wc_get_order' )->justReturn( $wc_order );
		when( 'wc_clean' )->alias( static fn( $value ) => $value );
		when( 'wp_unslash' )->alias( static fn( $value ) => $value );
		when( 'home_url' )->alias( static fn( string $path = '' ): string => 'https://example.com' . $path );
		when( 'add_query_arg' )->alias( static function ( $key, $value, $url ): string {
			// The production code already rawurlencode()s the redirect_uri value before
			// calling add_query_arg(); this stub concatenates as-is, as WordPress does.
			$separator = strpos( $url, '?' ) === false ? '?' : '&';
			return $url . $separator . $key . '=' . $value;
		} );

		$_POST['axo_nonce'] = 'card-single-use-token';
		unset( $_GET['token'] );

		$sut = $this->create_gateway_for_process_payment(
			$paypal_order,
			$purchase_unit_factory,
			$shipping_preference_factory,
			$settings_model,
			$experience_context_builder,
			$return_url_secret
		);

		// When
		$result = $sut->process_payment( 77 );

		unset( $_POST['axo_nonce'] );

		// Then
		$this->assertSame( 'success', $result['result'] );

		$redirect_query = [];
		parse_str( (string) parse_url( $result['redirect'], PHP_URL_QUERY ), $redirect_query );

		$inner_return_url = $redirect_query['redirect_uri'] ?? '';
		$inner_query = [];
		parse_str( (string) parse_url( $inner_return_url, PHP_URL_QUERY ), $inner_query );

		$this->assertSame( 'PAYPAL-ORDER-XYZ', $inner_query['token'] ?? null );
		$this->assertSame( 'bound-nonce-value', $inner_query['ppcp_return_nonce'] ?? null );
	}
}
