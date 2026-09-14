<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\SdkV6\Helper;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use WC_Customer;
use WC_Session;
use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\when;

class RecordedTaxBasisTest extends TestCase {
	use MockeryPHPUnitIntegration;
	use StubsWcSession;

	private RecordedTaxBasis $sut;

	public function setUp(): void {
		parent::setUp();

		// A shopper who has not chosen local pickup and a store that taxes on the
		// shipping address, unless a test says otherwise.
		when( 'apply_filters' )->justReturn( array( 'legacy_local_pickup', 'local_pickup' ) );
		when( 'wc_get_chosen_shipping_method_ids' )->justReturn( array( 'flat_rate' ) );
		when( 'get_option' )->justReturn( '' );

		$this->sut = new RecordedTaxBasis();
	}

	private function stub_wc( ?WC_Session $session, ?WC_Customer $customer = null ): void {
		when( 'WC' )->justReturn(
			(object) array(
				'session'  => $session,
				'customer' => $customer,
			)
		);
	}

	private function customer( array $billing, array $shipping ): WC_Customer {
		$customer = Mockery::mock( WC_Customer::class );
		$customer->allows( 'get_billing_country' )->andReturn( $billing['country'] ?? '' );
		$customer->allows( 'get_billing_state' )->andReturn( $billing['state'] ?? '' );
		$customer->allows( 'get_billing_postcode' )->andReturn( $billing['postcode'] ?? '' );
		$customer->allows( 'get_billing_city' )->andReturn( $billing['city'] ?? '' );
		$customer->allows( 'get_shipping_country' )->andReturn( $shipping['country'] ?? '' );
		$customer->allows( 'get_shipping_state' )->andReturn( $shipping['state'] ?? '' );
		$customer->allows( 'get_shipping_postcode' )->andReturn( $shipping['postcode'] ?? '' );
		$customer->allows( 'get_shipping_city' )->andReturn( $shipping['city'] ?? '' );

		return $customer;
	}

	private function basis_address(): array {
		return array(
			'country'  => 'US',
			'state'    => 'CA',
			'postcode' => '90210',
			'city'     => 'Beverly Hills',
		);
	}

	// ---------------------------------------------------------------------
	// set() / get()
	// ---------------------------------------------------------------------

	/**
	 * GIVEN a wallet-reported address that carries a country
	 * WHEN it is recorded and then read back
	 * THEN the same address is returned
	 */
	public function test_get_returns_the_address_that_was_set(): void {
		$store = array();
		$this->stub_wc( $this->session_with( $store ) );

		$this->sut->set( $this->basis_address() );

		$this->assertSame( $this->basis_address(), $this->sut->get() );
	}

	/**
	 * GIVEN a wallet-reported address with no country
	 * WHEN it is recorded
	 * THEN nothing is stored, because an address without a country is not a basis for tax
	 */
	public function test_set_ignores_an_address_without_a_country(): void {
		$store = array();
		$this->stub_wc( $this->session_with( $store ) );

		$this->sut->set(
			array(
				'state'    => 'CA',
				'postcode' => '90210',
			)
		);

		$this->assertSame( array(), $this->sut->get() );
	}

	/**
	 * GIVEN nothing was ever recorded
	 * WHEN the basis is read
	 * THEN an empty array is returned
	 */
	public function test_get_returns_empty_array_when_nothing_is_stored(): void {
		$store = array();
		$this->stub_wc( $this->session_with( $store ) );

		$this->assertSame( array(), $this->sut->get() );
	}

	// ---------------------------------------------------------------------
	// filter_taxable_address()
	// ---------------------------------------------------------------------

	/**
	 * GIVEN a recorded wallet address
	 * AND the store taxes on the shipping address, which the incoming address matches
	 * WHEN the taxable address filter runs
	 * THEN the recorded address is substituted as a positional [country, state, postcode, city] tuple
	 */
	public function test_filter_substitutes_the_recorded_address_when_shipping_based(): void {
		$store    = array();
		$customer = $this->customer(
			array(),
			array(
				'country'  => 'FR',
				'state'    => 'IDF',
				'postcode' => '75001',
				'city'     => 'Paris',
			)
		);
		$this->stub_wc( $this->session_with( $store ), $customer );
		when( 'time' )->justReturn( 1000 );
		when( 'get_option' )->justReturn( 'shipping' );

		$this->sut->set( $this->basis_address() );

		$result = $this->sut->filter_taxable_address( array( 'FR', 'IDF', '75001', 'Paris' ) );

		$this->assertSame( array( 'US', 'CA', '90210', 'Beverly Hills' ), $result );
	}

	/**
	 * GIVEN a recorded wallet address
	 * AND the store taxes on the billing address, which the incoming address matches
	 * WHEN the taxable address filter runs
	 * THEN the recorded address is substituted as a positional tuple
	 */
	public function test_filter_substitutes_the_recorded_address_when_billing_based(): void {
		$store    = array();
		$customer = $this->customer(
			array(
				'country'  => 'FR',
				'state'    => 'IDF',
				'postcode' => '75001',
				'city'     => 'Paris',
			),
			array()
		);
		$this->stub_wc( $this->session_with( $store ), $customer );
		when( 'time' )->justReturn( 1000 );
		when( 'get_option' )->justReturn( 'billing' );

		$this->sut->set( $this->basis_address() );

		$result = $this->sut->filter_taxable_address( array( 'FR', 'IDF', '75001', 'Paris' ) );

		$this->assertSame( array( 'US', 'CA', '90210', 'Beverly Hills' ), $result );
	}

	/**
	 * GIVEN nothing was recorded for this payment
	 * WHEN the taxable address filter runs
	 * THEN the incoming address is returned unchanged
	 */
	public function test_filter_passes_through_when_nothing_is_recorded(): void {
		$store = array();
		$this->stub_wc( $this->session_with( $store ) );

		$address = array( 'FR', 'IDF', '75001', 'Paris' );

		$this->assertSame( $address, $this->sut->filter_taxable_address( $address ) );
	}

	/**
	 * GIVEN a recorded wallet address
	 * AND the shopper has chosen a local pickup shipping method
	 * WHEN the taxable address filter runs
	 * THEN the incoming address is returned unchanged, because WooCommerce already applies the
	 * shop's own base address for pickup and the wallet's address must not override it
	 */
	public function test_filter_passes_through_for_local_pickup(): void {
		$store = array();
		$this->stub_wc( $this->session_with( $store ) );
		when( 'time' )->justReturn( 1000 );
		when( 'wc_get_chosen_shipping_method_ids' )->justReturn( array( 'local_pickup' ) );

		$this->sut->set( $this->basis_address() );

		$address = array( 'FR', 'IDF', '75001', 'Paris' );

		$this->assertSame( $address, $this->sut->filter_taxable_address( $address ) );
	}

	/**
	 * GIVEN a recorded wallet address
	 * AND the incoming address no longer matches the customer's own address for whichever
	 * field the store taxes on, meaning something else already substituted one (a block-cart
	 * pickup location, or a merchant's own tax plugin)
	 * WHEN the taxable address filter runs
	 * THEN the incoming address is returned unchanged, deferring to whatever substituted it
	 *
	 * @dataProvider already_substituted_address_provider
	 */
	public function test_filter_passes_through_when_address_already_substituted( string $tax_based_on, array $billing, array $shipping ): void {
		$store    = array();
		$customer = $this->customer( $billing, $shipping );
		$this->stub_wc( $this->session_with( $store ), $customer );
		when( 'time' )->justReturn( 1000 );
		when( 'get_option' )->justReturn( $tax_based_on );

		$this->sut->set( $this->basis_address() );

		$address = array( 'DE', 'BE', '10115', 'Berlin' );

		$this->assertSame( $address, $this->sut->filter_taxable_address( $address ) );
	}

	public function already_substituted_address_provider(): array {
		return array(
			'billing-based tax, address does not match the customer billing address'   => array(
				'billing',
				array(
					'country'  => 'FR',
					'state'    => 'IDF',
					'postcode' => '75001',
					'city'     => 'Paris',
				),
				array(),
			),
			'shipping-based tax, address does not match the customer shipping address' => array(
				'shipping',
				array(),
				array(
					'country'  => 'FR',
					'state'    => 'IDF',
					'postcode' => '75001',
					'city'     => 'Paris',
				),
			),
		);
	}
}
