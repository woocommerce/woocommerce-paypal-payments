<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Repository;

use Mockery;
use WooCommerce\PayPalCommerce\ApiClient\Helper\DccApplies;
use WooCommerce\PayPalCommerce\Settings\Data\Definition\FeaturesDefinition;
use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\when;

/**
 * @covers \WooCommerce\PayPalCommerce\ApiClient\Repository\PartnerReferralsData
 */
class PartnerReferralsDataTest extends TestCase
{
	private $dcc_applies;
	private $features_definition;
	private PartnerReferralsData $sut;

	public function setUp(): void
	{
		parent::setUp();

		$this->dcc_applies         = Mockery::mock( DccApplies::class );
		$this->features_definition = Mockery::mock( FeaturesDefinition::class );

		$this->sut = new PartnerReferralsData(
			$this->dcc_applies,
			$this->features_definition
		);
	}

	public function test_data_includes_seller_nonce_from_argument(): void
	{
		// Arrange
		$seller_nonce = bin2hex( random_bytes( 32 ) );

		$this->dcc_applies
			->shouldReceive( 'for_country_currency' )
			->andReturn( false );

		$this->features_definition
			->shouldReceive( 'is_feature_eligible' )
			->andReturn( false );

		when( 'apply_filters' )->returnArg( 2 );
		when( 'admin_url' )->justReturn( 'http://example.com/wp-admin/' );
		when( 'add_query_arg' )->returnArg( 1 );
		when( '__' )->returnArg( 1 );

		// When
		$result = $this->sut->data( array(), '', null, true, $seller_nonce );

		// Then
		$operations = $result['operations'][0]['api_integration_preference']['rest_api_integration']['first_party_details'];
		$this->assertSame( $seller_nonce, $operations['seller_nonce'] );
	}
}