<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Applepay;

use Mockery;
use Psr\Log\LoggerInterface;
use ReflectionMethod;
use WooCommerce\PayPalCommerce\Applepay\Assets\ApplePayDataObjectHttp;
use WooCommerce\PayPalCommerce\TestCase;

/**
 * @covers \WooCommerce\PayPalCommerce\Applepay\Assets\ApplePayDataObjectHttp
 */
class ApplePayDataObjectHttpTest extends TestCase {
	private ApplePayDataObjectHttp $sut;

	public function setUp(): void {
		parent::setUp();

		$logger    = Mockery::mock( LoggerInterface::class )->shouldIgnoreMissing();
		$this->sut = new ApplePayDataObjectHttp( $logger );
	}

	/**
	 * Invokes the protected simplified_address() method.
	 *
	 * The method is not part of the public API, but it is the exact seam where the buyer's
	 * state is dropped during the live shipping-contact callback (before the order is placed),
	 * so reflection is the only way to observe this behavior in isolation.
	 */
	private function simplified_address( array $contact_info ): array {
		$method = new ReflectionMethod( $this->sut, 'simplified_address' );
		$method->setAccessible( true );

		return $method->invoke( $this->sut, $contact_info );
	}

	/**
	 * GIVEN a shipping contact received during Apple Pay's live shipping-rate callback that
	 *       carries administrativeArea "NY" together with the already-required locality,
	 *       postalCode and countryCode
	 * WHEN the simplified address used to calculate shipping rates is built
	 * THEN the resulting address includes the buyer's state "NY"
	 */
	public function testSimplifiedAddressIncludesStateFromAdministrativeArea(): void {
		$address = $this->simplified_address(
			array(
				'locality'           => 'New York',
				'postalCode'         => '10001',
				'countryCode'        => 'us',
				'administrativeArea' => 'NY',
			)
		);

		$this->assertSame( 'NY', $address['state'] ?? null );
	}
}
