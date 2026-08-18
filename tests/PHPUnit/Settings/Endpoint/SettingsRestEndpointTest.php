<?php
declare( strict_types=1 );

namespace PHPUnit\Settings\Endpoint;

use Mockery;
use WP_REST_Request;
use WP_REST_Response;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsModel;
use WooCommerce\PayPalCommerce\Settings\Endpoint\SettingsRestEndpoint;
use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\when;

/**
 * The invoice prefix becomes part of identifiers the plugin sends to PayPal
 * (purchase-unit invoice_id, refund fallback, vault customer_id), so
 * update_details() must reject unsafe characters before anything is
 * persisted. This is a backstop for callers that bypass the settings UI,
 * e.g. the REST API directly or a Blueprints import.
 *
 * @covers \WooCommerce\PayPalCommerce\Settings\Endpoint\SettingsRestEndpoint
 */
class SettingsRestEndpointTest extends TestCase {

	public function setUp(): void {
		parent::setUp();
		when( 'rest_ensure_response' )->alias( static fn( $data ) => new WP_REST_Response( $data ) );
	}

	/**
	 * GIVEN a request whose invoicePrefix contains a space
	 * WHEN update_details() processes it
	 * THEN the response reports failure
	 * AND nothing is persisted to the settings model
	 *
	 * @dataProvider invalid_invoice_prefix_provider
	 */
	public function test_invalid_invoice_prefix_is_rejected_without_persisting( string $invalid_prefix ): void {
		$settings = Mockery::mock( SettingsModel::class );
		$settings->shouldNotReceive( 'from_array' );
		$settings->shouldNotReceive( 'save' );

		$endpoint = new SettingsRestEndpoint( $settings );

		$request = new WP_REST_Request( 'POST', '/settings' );
		$request->set_param( 'invoicePrefix', $invalid_prefix );

		$response = $endpoint->update_details( $request );
		$data     = $response->get_data();

		$this->assertFalse( $data['success'] );
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public function invalid_invoice_prefix_provider(): array {
		return array(
			'a trailing space is rejected'      => array( 'WC ' ),
			'an internal space is rejected'     => array( 'WC Store' ),
			'an at-sign is rejected'             => array( 'WC@1' ),
			'a slash is rejected'                => array( 'WC/1' ),
		);
	}

	/**
	 * GIVEN a request whose invoicePrefix only contains letters, numbers, hyphens or underscores
	 * WHEN update_details() processes it
	 * THEN the response reports success
	 * AND the settings model is updated and saved
	 *
	 * @dataProvider valid_invoice_prefix_provider
	 */
	public function test_valid_invoice_prefix_is_accepted_and_saved( string $valid_prefix ): void {
		$settings = Mockery::mock( SettingsModel::class );
		$settings->shouldReceive( 'from_array' )->once();
		$settings->shouldReceive( 'save' )->once();
		$settings->shouldReceive( 'to_array' )->andReturn( array() );

		$endpoint = new SettingsRestEndpoint( $settings );

		$request = new WP_REST_Request( 'POST', '/settings' );
		$request->set_param( 'invoicePrefix', $valid_prefix );

		$response = $endpoint->update_details( $request );
		$data     = $response->get_data();

		$this->assertTrue( $data['success'] );
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public function valid_invoice_prefix_provider(): array {
		return array(
			'a hyphen suffix is valid'      => array( 'WC-' ),
			'an underscore separator is valid' => array( 'Store_1' ),
			'plain alphanumerics are valid' => array( 'abc123' ),
		);
	}

	/**
	 * GIVEN a request with an empty invoicePrefix
	 * WHEN update_details() processes it
	 * THEN the response reports success
	 * AND the settings model is updated and saved, since consumers fall back to a default prefix
	 */
	public function test_empty_invoice_prefix_is_valid_and_saved(): void {
		$settings = Mockery::mock( SettingsModel::class );
		$settings->shouldReceive( 'from_array' )->once();
		$settings->shouldReceive( 'save' )->once();
		$settings->shouldReceive( 'to_array' )->andReturn( array() );

		$endpoint = new SettingsRestEndpoint( $settings );

		$request = new WP_REST_Request( 'POST', '/settings' );
		$request->set_param( 'invoicePrefix', '' );

		$response = $endpoint->update_details( $request );
		$data     = $response->get_data();

		$this->assertTrue( $data['success'] );
	}

	/**
	 * GIVEN a request that does not include an invoicePrefix at all
	 * WHEN update_details() processes it
	 * THEN the response reports success
	 * AND the settings model is updated and saved as normal
	 */
	public function test_missing_invoice_prefix_is_unaffected_and_saves_normally(): void {
		$settings = Mockery::mock( SettingsModel::class );
		$settings->shouldReceive( 'from_array' )->once();
		$settings->shouldReceive( 'save' )->once();
		$settings->shouldReceive( 'to_array' )->andReturn( array() );

		$endpoint = new SettingsRestEndpoint( $settings );

		$request = new WP_REST_Request( 'POST', '/settings' );
		$request->set_param( 'brandName', 'Acme' );

		$response = $endpoint->update_details( $request );
		$data     = $response->get_data();

		$this->assertTrue( $data['success'] );
	}
}
