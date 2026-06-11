<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\VaultComponent\Endpoint;

use Mockery;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PurchaseUnitFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\ShippingPreferenceFactory;
use WooCommerce\PayPalCommerce\Button\Endpoint\RequestData;
use WooCommerce\PayPalCommerce\TestCase;

class CreateVaultOrderEndpointTest extends TestCase {

	private CreateVaultOrderEndpoint $endpoint;

	public function setUp(): void {
		parent::setUp();

		$this->endpoint = new CreateVaultOrderEndpoint(
			Mockery::mock( RequestData::class ),
			Mockery::mock( OrderEndpoint::class ),
			Mockery::mock( PurchaseUnitFactory::class ),
			Mockery::mock( ShippingPreferenceFactory::class ),
			Mockery::mock( LoggerInterface::class )
		);
	}

	public function testStripRequestBodyReducesPurchaseUnitsToAmount(): void {
		$data = array(
			'purchase_units' => array(
				array(
					'amount'      => array( 'value' => '10.00' ),
					'items'       => array( array( 'name' => 'A' ) ),
					'description' => 'something',
				),
			),
		);

		$result = $this->endpoint->strip_request_body( $data );

		$this->assertSame(
			array( array( 'amount' => array( 'value' => '10.00' ) ) ),
			$result['purchase_units']
		);
	}

	public function testStripRequestBodyRemovesPayer(): void {
		$data = array( 'payer' => array( 'email_address' => 'buyer@example.com' ) );

		$result = $this->endpoint->strip_request_body( $data );

		$this->assertArrayNotHasKey( 'payer', $result );
	}

	public function testStripRequestBodyRemovesPaypalTokenFromArrayShape(): void {
		$data = array(
			'payment_source' => array(
				'paypal' => array(
					'token'              => array( 'id' => 'tok_1' ),
					'experience_context' => array( 'return_url' => 'https://example.com' ),
				),
			),
		);

		$result = $this->endpoint->strip_request_body( $data );

		$this->assertArrayNotHasKey( 'token', $result['payment_source']['paypal'] );
		$this->assertArrayHasKey( 'experience_context', $result['payment_source']['paypal'] );
	}

	public function testStripRequestBodyRemovesPaypalTokenFromObjectShape(): void {
		$paypal        = (object) array(
			'token'              => (object) array( 'id' => 'tok_1' ),
			'experience_context' => (object) array( 'return_url' => 'https://example.com' ),
		);
		$data          = array( 'payment_source' => array( 'paypal' => $paypal ) );

		$result = $this->endpoint->strip_request_body( $data );

		$this->assertFalse( property_exists( $result['payment_source']['paypal'], 'token' ) );
		$this->assertTrue( property_exists( $result['payment_source']['paypal'], 'experience_context' ) );
	}

	public function testStripRequestBodyLeavesUnrelatedDataUntouched(): void {
		$data = array( 'intent' => 'CAPTURE' );

		$result = $this->endpoint->strip_request_body( $data );

		$this->assertSame( array( 'intent' => 'CAPTURE' ), $result );
	}
}
