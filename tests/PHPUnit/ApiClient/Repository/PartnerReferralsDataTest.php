<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\ApiClient\Repository;

use Mockery;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\ApiClient\Helper\DccApplies;
use function Brain\Monkey\Functions\when;

/**
 * @group api
 * @group onboarding
 */
class PartnerReferralsDataTest extends TestCase {
	/**
	 * Expected nonce that should appear in the payload.
	 */
	private const DEFAULT_NONCE = 'a1233wtergfsdt4365tzrshgfbaewa36AGa1233wtergfsdt4365tzrshgfbaewa36AG';

	/**
	 * Sample URL which is used to mock the `admin_url()` response, used to build the return URL.
	 * Specifically, we want to verify the $path which is appended to the admin URL.
	 */
	private const ADMIN_URL = 'https://example.com/wp-admin/';

	/**
	 * A sample token that we add to the return URL.
	 * We pass this const to the `->data()` method to ensure it's appended at the end of the
	 * return URL as-is.
	 */
	private const TOKEN = 'SECURE_TOKEN';

	/**
	 * Expected return URL to see at in the payload, including the ppcpToken.
	 */
	private const RETURN_URL = 'https://example.com/wp-admin/admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway&ppcpToken=SECURE_TOKEN';

	private $testee;
	private $dccApplies;

	public function setUp() : void {
		parent::setUp();

		$this->dccApplies = Mockery::mock( DccApplies::class );
		$this->testee     = new PartnerReferralsData( $this->dccApplies );

		when( 'admin_url' )->alias( static fn( string $path ) => self::ADMIN_URL . $path );
		when( 'add_query_arg' )->justReturn( self::RETURN_URL );
	}

	/**
	 * Base structure of the API payload. Each test should modify the returned
	 * value of the method to meet its expectations.
	 *
	 * This avoids repeating the full structure, while also highlighting the
	 * specific changes that different params will generate.
	 *
	 * @return array
	 */
	private function getBaseExpectedArray() : array {
		return [
			'partner_config_override' => [
				'return_url'             => self::RETURN_URL,
				'return_url_description' => 'Return to your shop.',
				'show_add_credit_card'   => true,
			],
			'legal_consents'          => [
				[
					'type'    => 'SHARE_DATA_CONSENT',
					'granted' => true,
				],
			],
			'operations'              => [
				[
					'operation'                  => 'API_INTEGRATION',
					'api_integration_preference' => [
						'rest_api_integration' => [
							'integration_method'  => 'PAYPAL',
							'integration_type'    => 'FIRST_PARTY',
							'first_party_details' => [
								'features'     => [
									'PAYMENT',
									'REFUND',
									'ADVANCED_TRANSACTIONS_SEARCH',
									'TRACKING_SHIPMENT_READWRITE',
								],
								'seller_nonce' => self::DEFAULT_NONCE,
							],
						],
					],
				],
			],
		];
	}

	/**
	 * Data provider for testing flag combinations.
	 *
	 * @return array[] Test cases with [has_subscriptions, has_cards, is_acdc_eligible, expected_changes]
	 */
	public function flagCombinationsProvider() : array {
		return [
			'with subscriptions and cards, ACDC eligible' => [
				true,  // With subscription?
				true,  // With cards?
				true,  // ACDC eligible?
				[
					'capabilities'         => [ 'PAYPAL_WALLET_VAULTING_ADVANCED' ],
					'show_add_credit_card' => true,
					'has_vault_features'   => true,
				],
			],
			'with subscriptions, no cards, ACDC eligible' => [
				true,  // With subscription?
				false, // With cards?
				true,  // ACDC eligible?
				[
					'capabilities'         => [ 'PAYPAL_WALLET_VAULTING_ADVANCED' ],
					'show_add_credit_card' => false,
					'has_vault_features'   => true,
				],
			],
			'no subscriptions, with cards, ACDC eligible' => [
				false, // With subscription?
				true,  // With cards?
				true,  // ACDC eligible?
				[
					'show_add_credit_card' => true,
					'has_vault_features'   => false,
				],
			],
			'no subscriptions, no cards, ACDC eligible' => [
				false, // With subscription?
				false, // With cards?
				true,  // ACDC eligible?
				[
					'show_add_credit_card' => false,
					'has_vault_features'   => false,
				],
			],
			'with subscriptions and cards, ACDC is not eligible' => [
				true,  // With subscription?
				true,  // With cards?
				false, // ACDC eligible?
				[
					'show_add_credit_card' => true,
					'has_vault_features'   => true,
				],
			],
			'with subscriptions, no cards, ACDC is not eligible' => [
				true,  // With subscription?
				false, // With cards?
				false, // ACDC eligible?
				[
					'show_add_credit_card' => false,
					'has_vault_features'   => true,
				],
			],
			'no subscriptions, with cards, ACDC is not eligible' => [
				false, // With subscription?
				true,  // With cards?
				false, // ACDC eligible?
				[
					'show_add_credit_card' => true,
					'has_vault_features'   => false,
				],
			],
			'no subscriptions, no cards, ACDC is not eligible' => [
				false, // With subscription?
				false, // With cards?
				false, // ACDC eligible?
				[
					'show_add_credit_card' => false,
					'has_vault_features'   => false,
				],
			],
		];
	}

	/**
	 * Ensure the default "products" are derived from the DccApplies response.
	 */
	public function testDefaultValues() : void {
		/**
		 * Case 1: The data() method gets no parameters, and the DccApplies check
		 * returns TRUE. Onboarding payload should indicate "PPCP".
		 */
		$this->dccApplies->expects( 'for_country_currency' )->andReturn( true );
		$result = $this->testee->data();
		$this->assertEquals( [ 'PPCP' ], $result['products'] );

		/**
		 * Case 2: The data() method gets no parameters, and the DccApplies check
		 * returns FALSE. Onboarding payload should indicate "EXPRESS_CHECKOUT".
		 */
		$this->dccApplies->expects( 'for_country_currency' )->andReturn( false );
		$result = $this->testee->data();
		$this->assertEquals( [ 'EXPRESS_CHECKOUT' ], $result['products'] );
	}

	/**
	 * Ensure the generated API payload is stable and contains the expected values.
	 *
	 * The test only verifies the "products" and "token" arguments, as those are the
	 * core params present in the legacy and new UI.
	 */
	public function testDataStructure() : void {
		/**
		 * Undefined subscription: Keep vaulting in first-party, but don't add the capability.
		 */
		$result = $this->testee->data( [ 'PPCP' ], self::TOKEN );
		$this->dccApplies->shouldNotHaveReceived( 'for_country_currency' );

		$expected = $this->getBaseExpectedArray();

		$expected['products'] = [ 'PPCP' ];

		$expected['operations'][0]['api_integration_preference']['rest_api_integration']['first_party_details']['features'][] = 'FUTURE_PAYMENT';
		$expected['operations'][0]['api_integration_preference']['rest_api_integration']['first_party_details']['features'][] = 'VAULT';

		$this->assertArrayNotHasKey( 'capabilities', $expected );
		$this->assertEquals( $expected, $result );
	}

	/**
	 * Test how different flag combinations affect the data structure.
	 * Those flags are present in the new UI.
	 *
	 * @dataProvider flagCombinationsProvider
	 */
	public function testDataStructureWithFlags( bool $has_subscriptions, bool $has_cards, bool $is_acdc_eligible, array $expected_changes ) : void {
		$this->dccApplies->shouldReceive('for_country_currency')->andReturn($is_acdc_eligible);

		$result   = $this->testee->data( [ 'PPCP' ], self::TOKEN, $has_subscriptions, $has_cards );
		$expected = $this->getBaseExpectedArray();

		$expected['products'] = [ 'PPCP' ];

		if ( isset( $expected_changes['capabilities'] ) ) {
			$expected['capabilities'] = $expected_changes['capabilities'];
		} else {
			$this->assertArrayNotHasKey( 'capabilities', $expected );
		}

		$expected['partner_config_override']['show_add_credit_card'] = $expected_changes['show_add_credit_card'];

		if ( $has_subscriptions ) {
			$expected['operations'][0]['api_integration_preference']['rest_api_integration']['first_party_details']['features'][] = 'BILLING_AGREEMENT';
		}

		if ( $expected_changes['has_vault_features'] ) {
			$expected['operations'][0]['api_integration_preference']['rest_api_integration']['first_party_details']['features'][] = 'FUTURE_PAYMENT';
			$expected['operations'][0]['api_integration_preference']['rest_api_integration']['first_party_details']['features'][] = 'VAULT';
		} else {
			// Double-check that the features are not present in our expected array
			$this->assertNotContains( 'FUTURE_PAYMENT', $expected['operations'][0]['api_integration_preference']['rest_api_integration']['first_party_details']['features'] );
			$this->assertNotContains( 'VAULT', $expected['operations'][0]['api_integration_preference']['rest_api_integration']['first_party_details']['features'] );
		}

		$this->assertEquals( $expected, $result );
	}
}
