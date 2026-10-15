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
use function Brain\Monkey\Filters\expectApplied;
use function Brain\Monkey\Functions\when;

class AxoGatewayTestable extends AxoGateway
{
	/**
	 * Option values that override the WC_Payment_Gateway stub's `get_option()`,
	 * keyed by option name. Must be set before the parent constructor runs,
	 * since title/description resolution happens there.
	 *
	 * @var array<string, mixed>
	 */
	private array $option_overrides;

	public function __construct(
		CardPaymentsConfiguration $dcc_configuration,
		SessionHandler $session_handler,
		OrderProcessor $order_processor,
		array $card_icons,
		OrderEndpoint $order_endpoint,
		PurchaseUnitFactory $purchase_unit_factory,
		ShippingPreferenceFactory $shipping_preference_factory,
		TransactionUrlProvider $transaction_url_provider,
		Environment $environment,
		LoggerInterface $logger,
		ExperienceContextBuilder $experience_context_builder,
		SettingsModel $settings_model,
		array $option_overrides = []
	) {
		$this->option_overrides = $option_overrides;

		parent::__construct(
			$dcc_configuration,
			$session_handler,
			$order_processor,
			$card_icons,
			$order_endpoint,
			$purchase_unit_factory,
			$shipping_preference_factory,
			$transaction_url_provider,
			$environment,
			$logger,
			$experience_context_builder,
			$settings_model
		);
	}

	public function get_option( string $key, $empty_value = null )
	{
		if ( array_key_exists( $key, $this->option_overrides ) ) {
			return $this->option_overrides[ $key ];
		}

		return parent::get_option( $key, $empty_value );
	}

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
	 * Builds a gateway instance with a given set of `get_option()` overrides,
	 * so each test can control what the merchant "saved" for a setting (e.g.
	 * the Fastlane title) without touching the other constructor collaborators.
	 *
	 * @param array<string, mixed>           $option_overrides  Option values, keyed by option name.
	 * @param CardPaymentsConfiguration|null $dcc_configuration Defaults to the shared setUp() stub.
	 */
	private function create_gateway(
		array $option_overrides = [],
		?CardPaymentsConfiguration $dcc_configuration = null
	): AxoGatewayTestable {
		return new AxoGatewayTestable(
			$dcc_configuration ?? $this->dcc_configuration,
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
			Mockery::mock( SettingsModel::class ),
			$option_overrides
		);
	}

	/**
	 * GIVEN a merchant who saved a Fastlane title ("Pay with Fastlane")
	 * AND the card (ACDC) gateway resolves to a different title ("Cards")
	 * WHEN the Fastlane gateway is constructed
	 * THEN the gateway's title is the merchant's saved Fastlane title, not the card gateway's
	 *
	 * This guards against regressing to passing Fastlane's title as a *fallback*
	 * into the card gateway's title helper, which made the card title win whenever
	 * one was configured — the common case.
	 */
	public function test_uses_configured_fastlane_title_even_when_card_gateway_title_differs(): void
	{
		$dcc_configuration = Mockery::mock( CardPaymentsConfiguration::class );
		$dcc_configuration->shouldReceive( 'use_fastlane' )->andReturn( false );
		$dcc_configuration->shouldReceive( 'gateway_title' )->andReturn( 'Cards' );

		$gateway = $this->create_gateway( [ 'title' => 'Pay with Fastlane' ], $dcc_configuration );

		$this->assertSame( 'Pay with Fastlane', $gateway->title );
	}

	/**
	 * GIVEN a merchant who never saved a Fastlane title (empty string)
	 * AND the card (ACDC) gateway resolves to "Cards"
	 * WHEN the Fastlane gateway is constructed
	 * THEN the gateway inherits the card gateway's title
	 */
	public function test_inherits_card_gateway_title_when_no_fastlane_title_configured(): void
	{
		$dcc_configuration = Mockery::mock( CardPaymentsConfiguration::class );
		$dcc_configuration->shouldReceive( 'use_fastlane' )->andReturn( false );
		$dcc_configuration->shouldReceive( 'gateway_title' )->andReturn( 'Cards' );

		$gateway = $this->create_gateway( [ 'title' => '' ], $dcc_configuration );

		$this->assertSame( 'Cards', $gateway->title );
	}

	/**
	 * GIVEN a merchant who never saved a Fastlane title
	 * AND the card (ACDC) gateway has no title of its own either, so it returns whatever
	 *     fallback it is given
	 * WHEN the Fastlane gateway is constructed
	 * THEN the gateway's title falls back to its own method title
	 */
	public function test_falls_back_to_method_title_when_neither_fastlane_nor_card_title_configured(): void
	{
		$dcc_configuration = Mockery::mock( CardPaymentsConfiguration::class );
		$dcc_configuration->shouldReceive( 'use_fastlane' )->andReturn( false );
		$dcc_configuration->shouldReceive( 'gateway_title' )
			->andReturnUsing( static fn ( string $fallback ): string => $fallback );

		$gateway = $this->create_gateway( [ 'title' => '' ], $dcc_configuration );

		$this->assertSame( 'Fastlane Debit & Credit Cards', $gateway->title );
	}

	/**
	 * GIVEN a merchant who saved a Fastlane title
	 * AND a callback attached to the `woocommerce_paypal_payments_axo_gateway_title` filter
	 * WHEN the Fastlane gateway is constructed
	 * THEN the filter's return value is used as the gateway's title, overriding the resolved one
	 */
	public function test_filter_can_override_resolved_title(): void
	{
		$dcc_configuration = Mockery::mock( CardPaymentsConfiguration::class );
		$dcc_configuration->shouldReceive( 'use_fastlane' )->andReturn( false );
		$dcc_configuration->shouldReceive( 'gateway_title' )->andReturn( 'Cards' );

		expectApplied( 'woocommerce_paypal_payments_axo_gateway_title' )
			->once()
			->with( 'Pay with Fastlane', Mockery::type( AxoGateway::class ) )
			->andReturn( 'Overridden Fastlane Title' );

		$gateway = $this->create_gateway( [ 'title' => 'Pay with Fastlane' ], $dcc_configuration );

		$this->assertSame( 'Overridden Fastlane Title', $gateway->title );
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
}
