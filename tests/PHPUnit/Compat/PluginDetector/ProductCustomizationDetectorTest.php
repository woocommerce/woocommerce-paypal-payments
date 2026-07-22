<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Compat\PluginDetector;

use Mockery;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\TestCase;

/**
 * @covers \WooCommerce\PayPalCommerce\Compat\PluginDetector\ProductCustomizationDetector
 */
class ProductCustomizationDetectorTest extends TestCase {

	/**
	 * @var PluginDetectorInterface|Mockery\MockInterface
	 */
	private $plugin_detector;

	/**
	 * @var LoggerInterface|Mockery\MockInterface
	 */
	private $logger;

	private ProductCustomizationDetector $sut;

	public function setUp(): void {
		parent::setUp();

		$this->plugin_detector = Mockery::mock( PluginDetectorInterface::class );
		$this->logger          = Mockery::mock( LoggerInterface::class );

		$this->sut = new ProductCustomizationDetector( $this->plugin_detector, $this->logger );
	}

	/**
	 * @return array<string, bool>
	 */
	private function all_inactive(): array {
		return array(
			'woocommerce-subscriptions'        => false,
			'woocommerce-gift-cards'           => false,
			'woocommerce-product-bundles'      => false,
			'woocommerce-product-addons'       => false,
			'woocommerce-min-max-quantities'   => false,
			'woocommerce-composite-products'   => false,
			'woocommerce-shipping-per-product' => false,
			'woocommerce-deposits'             => false,
		);
	}

	/**
	 * @test
	 */
	public function test_all_false_when_no_plugin_is_active(): void {
		$this->plugin_detector->shouldReceive( 'scan' )->once()->andReturn( $this->all_inactive() );
		$this->logger->shouldNotReceive( 'warning' );

		// An unconfigured Mockery mock throws on any unexpected method call,
		// so this also proves no check method ever touches the product.
		$product = Mockery::mock( 'WC_Product' );

		$result = $this->sut->scan( $product );

		$this->assertSame( $this->all_inactive(), $result );
	}

	/**
	 * @test
	 * @dataProvider api_based_plugins
	 */
	public function test_logs_warning_and_returns_false_when_plugin_method_is_missing( string $plugin ): void {
		$active_plugins            = $this->all_inactive();
		$active_plugins[ $plugin ] = true;

		$this->plugin_detector->shouldReceive( 'scan' )->once()->andReturn( $active_plugins );
		$this->logger->shouldReceive( 'warning' )
			->once()
			->with(
				Mockery::on(
					static function ( string $message ) use ( $plugin ): bool {
						return false !== strpos( $message, $plugin ) && false !== strpos( $message, 'does not exist' );
					}
				)
			);

		$product = Mockery::mock( 'WC_Product' );

		$result = $this->sut->scan( $product );

		$this->assertFalse( $result[ $plugin ] );
	}

	/**
	 * Returns the plugins detected via a plugin API method call, as
	 * opposed to the type-based and meta-based plugins covered elsewhere.
	 *
	 * @return array<string, array{string}>
	 */
	public static function api_based_plugins(): array {
		return array(
			'subscriptions'   => array( 'woocommerce-subscriptions' ),
			'gift cards'      => array( 'woocommerce-gift-cards' ),
			'product add-ons' => array( 'woocommerce-product-addons' ),
			'deposits'        => array( 'woocommerce-deposits' ),
		);
	}

	/**
	 * @test
	 * @dataProvider type_based_plugins
	 */
	public function test_type_based_checks_follow_is_type( string $plugin, string $product_type, bool $is_type, bool $expected ): void {
		$active_plugins            = $this->all_inactive();
		$active_plugins[ $plugin ] = true;

		$this->plugin_detector->shouldReceive( 'scan' )->once()->andReturn( $active_plugins );

		$product = Mockery::mock( 'WC_Product' );
		$product->allows( 'is_type' )->with( $product_type )->andReturn( $is_type );

		$result = $this->sut->scan( $product );

		$this->assertSame( $expected, $result[ $plugin ] );
	}

	/**
	 * Returns the plugins detected via $product->is_type(), as opposed to
	 * the API-method-based and meta-based plugins covered elsewhere.
	 *
	 * @return array<string, array{string, string, bool, bool}>
	 */
	public static function type_based_plugins(): array {
		return array(
			'bundle - matches'        => array( 'woocommerce-product-bundles', 'bundle', true, true ),
			'bundle - does not match' => array( 'woocommerce-product-bundles', 'bundle', false, false ),
			'composite - matches'     => array( 'woocommerce-composite-products', 'composite', true, true ),
			'composite - no match'    => array( 'woocommerce-composite-products', 'composite', false, false ),
		);
	}

	/**
	 * @test
	 * @dataProvider shipping_per_product_meta_values
	 */
	public function test_shipping_per_product_requires_yes_value( string $meta_value, bool $expected ): void {
		$active_plugins                                     = $this->all_inactive();
		$active_plugins['woocommerce-shipping-per-product'] = true;

		$this->plugin_detector->shouldReceive( 'scan' )->once()->andReturn( $active_plugins );

		$product = Mockery::mock( 'WC_Product' );
		$product->allows( 'get_meta' )->with( '_per_product_shipping', true )->andReturn( $meta_value );

		$result = $this->sut->scan( $product );

		$this->assertSame( $expected, $result['woocommerce-shipping-per-product'] );
	}

	/** @return array<string, array{string, bool}> */
	public static function shipping_per_product_meta_values(): array {
		return array(
			'enabled'       => array( 'yes', true ),
			'explicitly no' => array( 'no', false ),
			'never set'     => array( '', false ),
		);
	}

	/**
	 * @test
	 * @dataProvider min_max_quantity_meta_combinations
	 */
	public function test_min_max_quantities_for_simple_products( string $minimum, string $maximum, string $group, bool $expected ): void {
		$active_plugins                                   = $this->all_inactive();
		$active_plugins['woocommerce-min-max-quantities'] = true;

		$this->plugin_detector->shouldReceive( 'scan' )->once()->andReturn( $active_plugins );

		$product = Mockery::mock( 'WC_Product_Simple' );
		$product->allows( 'get_meta' )->with( 'minimum_allowed_quantity', true )->andReturn( $minimum );
		$product->allows( 'get_meta' )->with( 'maximum_allowed_quantity', true )->andReturn( $maximum );
		$product->allows( 'get_meta' )->with( 'group_of_quantity', true )->andReturn( $group );

		$result = $this->sut->scan( $product );

		$this->assertSame( $expected, $result['woocommerce-min-max-quantities'] );
	}

	/** @return array<string, array{string, string, string, bool}> */
	public static function min_max_quantity_meta_combinations(): array {
		return array(
			'minimum set' => array( '5', '', '', true ),
			'maximum set' => array( '', '3', '', true ),
			'group set'   => array( '', '', '2', true ),
			'none set'    => array( '', '', '', false ),
		);
	}

	/**
	 * Regression test: variations must be checked against the
	 * `variation_`-prefixed meta keys, not the plain product-level ones
	 * (which the plugin never writes onto a variation).
	 *
	 * @test
	 */
	public function test_min_max_quantities_ignores_plain_keys_for_variations(): void {
		$active_plugins                                   = $this->all_inactive();
		$active_plugins['woocommerce-min-max-quantities'] = true;

		$this->plugin_detector->shouldReceive( 'scan' )->once()->andReturn( $active_plugins );

		$product = Mockery::mock( 'WC_Product_Variation' );
		$product->allows( 'get_meta' )->with( 'variation_minimum_allowed_quantity', true )->andReturn( '' );
		$product->allows( 'get_meta' )->with( 'variation_maximum_allowed_quantity', true )->andReturn( '' );
		$product->allows( 'get_meta' )->with( 'variation_group_of_quantity', true )->andReturn( '' );
		// Stale/irrelevant parent-style meta that must NOT be consulted for a variation.
		$product->allows( 'get_meta' )->with( 'minimum_allowed_quantity', true )->andReturn( '999' );

		$result = $this->sut->scan( $product );

		$this->assertFalse( $result['woocommerce-min-max-quantities'] );
	}

	/**
	 * @test
	 */
	public function test_min_max_quantities_detects_variation_specific_override(): void {
		$active_plugins                                   = $this->all_inactive();
		$active_plugins['woocommerce-min-max-quantities'] = true;

		$this->plugin_detector->shouldReceive( 'scan' )->once()->andReturn( $active_plugins );

		$product = Mockery::mock( 'WC_Product_Variation' );
		$product->allows( 'get_meta' )->with( 'variation_minimum_allowed_quantity', true )->andReturn( '3' );
		$product->allows( 'get_meta' )->with( 'variation_maximum_allowed_quantity', true )->andReturn( '' );
		$product->allows( 'get_meta' )->with( 'variation_group_of_quantity', true )->andReturn( '' );

		$result = $this->sut->scan( $product );

		$this->assertTrue( $result['woocommerce-min-max-quantities'] );
	}

	/**
	 * @test
	 */
	public function test_memoizes_plugin_detector_scan_across_multiple_calls(): void {
		$this->plugin_detector->shouldReceive( 'scan' )->once()->andReturn( $this->all_inactive() );

		$this->sut->scan( Mockery::mock( 'WC_Product' ) );
		$this->sut->scan( Mockery::mock( 'WC_Product' ) );

		$this->addToAssertionCount( 1 );
	}
}
