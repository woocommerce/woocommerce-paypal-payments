<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Registration;

use Mockery;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\StoreSync\Merchant\MerchantMetadata;
use WooCommerce\PayPalCommerce\StoreSync\Merchant\MerchantMetadataProvider;

/**
 * @covers \WooCommerce\PayPalCommerce\StoreSync\Registration\RegistrationEligibility
 */
class RegistrationEligibilityTest extends TestCase {

	/**
	 * @dataProvider eligibility_scenarios_provider
	 */
	public function test_eligibility_for_agentic_commerce( bool $is_eligible, string $store_country ): void {
		$data_provider = Mockery::mock( MerchantMetadataProvider::class );
		$data_provider->allows( 'get_metadata' )
			->andReturnUsing( static fn() => new MerchantMetadata(
				'Store Name',
				'https://example.com',
				'https://example.com/wp-json/wc/store/v1',
				$store_country, // test value
				'',
				'MERCHANT123',
				'https://example.com',
				$store_country
			) );

		$result = new RegistrationEligibility( $data_provider );

		$this->assertSame( $is_eligible, $result->is_eligible() );
	}

	public function eligibility_scenarios_provider(): array {
		return array(
			'us-store'  => array( true, 'US' ),
			'de-store'  => array( false, 'DE' ),
			'uk-store'  => array( false, 'UK' ),
			'no-store'  => array( false, '' ),
			'us-lower'  => array( true, 'us' ),
			'us-spaces' => array( true, ' us ' ),
		);
	}
}
