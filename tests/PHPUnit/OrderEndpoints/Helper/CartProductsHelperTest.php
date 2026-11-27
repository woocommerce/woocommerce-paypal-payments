<?php
declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\OrderEndpoints\Helper;

use Exception;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Filters\expectApplied;
use function Brain\Monkey\Functions\when;

/**
 * @covers \WooCommerce\PayPalCommerce\OrderEndpoints\Helper\CartProductsHelper
 */
class CartProductsHelperTest extends TestCase {
	use MockeryPHPUnitIntegration;

	private const FILTER = 'woocommerce_add_to_cart_validation';

	/** @var MockInterface&\WC_Cart */
	private $cart;

	private CartProductsHelper $sut;

	public function setUp(): void {
		parent::setUp();

		$this->cart = Mockery::mock( \WC_Cart::class );

		$this->sut = new CartProductsHelper( Mockery::mock( \WC_Data_Store::class ) );
		$this->sut->set_cart( $this->cart );

		$_POST = array();
	}

	public function tearDown(): void {
		$_POST = array();

		parent::tearDown();
	}

	private function simple_product_entry( int $id, int $quantity ): array {
		$product = Mockery::mock( \WC_Product::class );
		$product->allows( 'get_id' )->andReturn( $id );
		$product->allows( 'is_type' )->andReturn( false );

		return array(
			'product'    => $product,
			'quantity'   => $quantity,
			'variations' => array(),
			'booking'    => null,
			'extra'      => null,
		);
	}

	private function booking_product_entry( int $id, array $booking ): array {
		$product = Mockery::mock( \WC_Product::class );
		$product->allows( 'get_id' )->andReturn( $id );
		$product->allows( 'is_type' )->andReturnUsing(
			static function ( string $type ): bool {
				return 'booking' === $type;
			}
		);

		return array(
			'product'    => $product,
			'quantity'   => 1,
			'variations' => array(),
			'booking'    => $booking,
			'extra'      => null,
		);
	}

	/**
	 * Runs add_products() for a booking and returns the $_POST seen by the validation filter.
	 */
	private function post_seen_by_validation( array $booking ): array {
		when( 'wc_bookings_get_posted_data' )->justReturn( array() );
		$this->cart->allows( 'add_to_cart' )->andReturn( 'cart-item-key' );

		$post_during_validation = null;
		expectApplied( self::FILTER )
			->once()
			->andReturnUsing(
				static function () use ( &$post_during_validation ): bool {
					$post_during_validation = $_POST;
					return true;
				}
			);

		$this->sut->add_products( array( $this->booking_product_entry( 7, $booking ) ) );

		return $post_during_validation;
	}

	/**
	 * GIVEN a booking product whose form data has wc_bookings_field keys
	 * WHEN the product is added to the cart
	 * THEN those fields are in $_POST by the time the add-to-cart validation runs
	 */
	public function test_booking_fields_reach_post_before_validation_runs(): void {
		$post = $this->post_seen_by_validation(
			array( 'wc_bookings_field_start_date' => '2026-12-01' )
		);

		$this->assertSame( '2026-12-01', $post['wc_bookings_field_start_date'] ?? null );
	}

	/**
	 * GIVEN a booking product whose form data also has keys without the wc_bookings_field prefix
	 * WHEN the product is added to the cart
	 * THEN those other keys are not copied into $_POST
	 */
	public function test_booking_data_without_field_prefix_is_not_copied_to_post(): void {
		$post = $this->post_seen_by_validation(
			array(
				'wc_bookings_field_start_date' => '2026-12-01',
				'unrelated_key'                => 'value',
			)
		);

		$this->assertArrayNotHasKey( 'unrelated_key', $post );
	}

	/**
	 * GIVEN a booking product and validation switched off
	 * WHEN the product is added to the cart
	 * THEN the wc_bookings_field data is still copied into $_POST
	 */
	public function test_booking_fields_reach_post_when_validation_is_disabled(): void {
		when( 'wc_bookings_get_posted_data' )->justReturn( array() );
		$this->cart->allows( 'add_to_cart' )->andReturn( 'cart-item-key' );

		$this->sut->add_products(
			array( $this->booking_product_entry( 7, array( 'wc_bookings_field_start_date' => '2026-12-01' ) ) ),
			false
		);

		$this->assertSame( '2026-12-01', $_POST['wc_bookings_field_start_date'] ?? null );
	}

	/**
	 * GIVEN a booking product
	 * WHEN the product is added to the cart
	 * THEN the validation filter receives the product ID, a quantity of 1, variation ID 0 and no attributes
	 * AND the booking data as cart item data
	 */
	public function test_booking_validation_receives_booking_as_cart_item_data_with_quantity_one(): void {
		$booking_data = array( 'duration' => 2 );
		when( 'wc_bookings_get_posted_data' )->justReturn( $booking_data );
		$this->cart->allows( 'add_to_cart' )->andReturn( 'cart-item-key' );

		expectApplied( self::FILTER )
			->once()
			->with( true, 7, 1, 0, array(), array( 'booking' => $booking_data ) )
			->andReturn( true );

		$this->sut->add_products( array( $this->booking_product_entry( 7, array() ) ) );
	}

	/**
	 * GIVEN a simple product that the add-to-cart validation refuses
	 * WHEN the product is added to the cart
	 * THEN adding fails with an exception
	 * AND the product is never added to the cart
	 */
	public function test_refused_product_throws_and_is_not_added_to_cart(): void {
		expectApplied( self::FILTER )
			->once()
			->with( true, 12, 3, 0, array(), array() )
			->andReturn( false );
		$this->cart->shouldNotReceive( 'add_to_cart' );

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Error adding products to cart.' );

		$this->sut->add_products( array( $this->simple_product_entry( 12, 3 ) ) );
	}

	/**
	 * GIVEN several products where the add-to-cart validation refuses the first one
	 * WHEN the products are added to the cart
	 * THEN adding fails with an exception
	 * AND the later products are neither validated nor added to the cart
	 */
	public function test_products_after_a_refused_product_are_not_validated_or_added(): void {
		expectApplied( self::FILTER )
			->once()
			->with( true, 12, 3, 0, array(), array() )
			->andReturn( false );
		$this->cart->shouldNotReceive( 'add_to_cart' );

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Error adding products to cart.' );

		$this->sut->add_products(
			array(
				$this->simple_product_entry( 12, 3 ),
				$this->simple_product_entry( 15, 2 ),
			)
		);
	}

	/**
	 * GIVEN a simple product that the add-to-cart validation would refuse
	 * WHEN the product is added with validation switched off
	 * THEN the validation filter is never applied
	 * AND the product is added to the cart
	 */
	public function test_validation_is_skipped_when_disabled(): void {
		expectApplied( self::FILTER )->never();
		$this->cart->expects( 'add_to_cart' )->once()->with( 12, 3, 0, array(), array() )->andReturn( 'cart-item-key' );

		$result = $this->sut->add_products( array( $this->simple_product_entry( 12, 3 ) ), false );

		$this->assertTrue( $result );
		$this->assertSame( array( 'cart-item-key' ), $this->sut->cart_item_keys() );
	}
}
