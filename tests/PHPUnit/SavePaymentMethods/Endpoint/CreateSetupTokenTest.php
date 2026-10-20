<?php

declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\SavePaymentMethods\Endpoint;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PaymentMethodTokensEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentSource;
use WooCommerce\PayPalCommerce\OrderEndpoints\Endpoint\RequestData;
use WooCommerce\PayPalCommerce\Button\Exception\NonceValidationException;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

class CreateSetupTokenTest extends TestCase {

	use MockeryPHPUnitIntegration;

	/** @var RequestData&\Mockery\MockInterface */
	private $request_data;

	/** @var PaymentMethodTokensEndpoint&\Mockery\MockInterface */
	private $tokens_endpoint;

	/** @var CreateSetupToken */
	private $sut;

	public function setUp(): void {
		parent::setUp();

		$this->request_data    = Mockery::mock( RequestData::class );
		$this->tokens_endpoint = Mockery::mock( PaymentMethodTokensEndpoint::class );

		$this->sut = new CreateSetupToken(
			$this->request_data,
			$this->tokens_endpoint
		);

		// Common WP stubs used by handle_request(). get_user_meta is intentionally
		// NOT stubbed here: a shared when() stub would shadow the per-test
		// expect( 'get_user_meta' ) in test_customer_id_comes_from_user_meta. Each
		// test that reaches it stubs it locally instead.
		when( 'get_current_user_id' )->justReturn( 7 );
		when( 'wc_get_account_endpoint_url' )->justReturn( 'https://example.com/my-account/' );
		when( 'wp_send_json_success' )->justReturn( null );
	}

	// -------------------------------------------------------------------------
	// Data provider
	// -------------------------------------------------------------------------

	/**
	 * Scenarios for payment_method and verification_method combinations.
	 *
	 * Columns: request_data array, expected PaymentSource name, expected properties check callable.
	 */
	public function payment_source_scenarios(): array {
		$cc_id = CreditCardGateway::ID;

		return [
			'credit card with SCA_ALWAYS sets verification_method and usage_type' => [
				'request'              => array(
					'payment_method'     => $cc_id,
					'verification_method' => 'SCA_ALWAYS',
				),
				'expected_name'        => 'card',
				'has_verification'     => true,
				'verification_method'  => 'SCA_ALWAYS',
			],
			'credit card with SCA_WHEN_REQUIRED sets verification_method and usage_type' => [
				'request'              => array(
					'payment_method'     => $cc_id,
					'verification_method' => 'SCA_WHEN_REQUIRED',
				),
				'expected_name'        => 'card',
				'has_verification'     => true,
				'verification_method'  => 'SCA_WHEN_REQUIRED',
			],
			'credit card with empty verification_method produces empty properties object' => [
				'request'              => array(
					'payment_method'     => $cc_id,
					'verification_method' => '',
				),
				'expected_name'        => 'card',
				'has_verification'     => false,
				'verification_method'  => '',
			],
			'paypal payment method produces paypal source with usage_type and experience_context' => [
				'request'              => array(
					'payment_method' => 'ppcp-gateway',
				),
				'expected_name'        => 'paypal',
				'has_verification'     => false,
				'verification_method'  => '',
			],
		];
	}

	// -------------------------------------------------------------------------
	// Tests
	// -------------------------------------------------------------------------

	/**
	 * GIVEN a logged-in user with a stored PayPal customer ID
	 * WHEN handle_request() is called with various payment_method / verification_method combos
	 * THEN setup_tokens() receives a PaymentSource with the correct name and properties
	 * AND the customer_id passed comes from get_user_meta(..., '_ppcp_target_customer_id', ...)
	 *
	 * @dataProvider payment_source_scenarios
	 */
	public function test_setup_tokens_receives_correct_payment_source(
		array $request,
		string $expected_name,
		bool $has_verification,
		string $verification_method
	): void {
		$this->request_data
			->shouldReceive( 'read_request' )
			->once()
			->with( CreateSetupToken::ENDPOINT )
			->andReturn( $request );

		when( 'get_user_meta' )->justReturn( 'cust-abc123' );

		/** @var PaymentSource|null $captured_source */
		$captured_source = null;

		$this->tokens_endpoint
			->shouldReceive( 'setup_tokens' )
			->once()
			->withArgs( function ( PaymentSource $source, string $customer_id ) use ( &$captured_source ): bool {
				$captured_source = $source;
				return true;
			} )
			->andReturn( new \stdClass() );

		$this->sut->handle_request();

		$this->assertNotNull( $captured_source, 'setup_tokens() was not called' );
		$this->assertSame( $expected_name, $captured_source->name() );

		$props = $captured_source->properties();

		if ( $has_verification ) {
			$this->assertSame(
				$verification_method,
				$props->verification_method,
				'verification_method property should match the value passed in'
			);
			$this->assertSame( 'MERCHANT', $props->usage_type );
			$this->assertTrue(
				property_exists( $props, 'experience_context' ),
				'experience_context property should be set'
			);
		} else {
			// For empty/non-SCA credit card: no properties set.
			// For paypal: usage_type + experience_context are set but no verification_method.
			if ( $expected_name === 'card' ) {
				// Empty properties object (no verification_method, no usage_type).
				$this->assertFalse(
					isset( $props->verification_method ),
					'Empty-verification card should produce no verification_method property'
				);
				$this->assertFalse(
					isset( $props->usage_type ),
					'Empty-verification card should produce no usage_type property'
				);
			} else {
				// paypal source always gets usage_type + experience_context.
				$this->assertSame( 'MERCHANT', $props->usage_type );
				$this->assertTrue(
				property_exists( $props, 'experience_context' ),
				'experience_context property should be set'
			);
			}
		}
	}

	/**
	 * GIVEN a logged-in user with customer ID 'cust-abc123' stored in user meta
	 * WHEN handle_request() is called
	 * THEN setup_tokens() receives 'cust-abc123' as the customer_id argument
	 */
	public function test_customer_id_comes_from_user_meta(): void {
		$this->request_data
			->shouldReceive( 'read_request' )
			->once()
			->andReturn( array( 'payment_method' => 'ppcp-gateway' ) );

		$captured_customer_id = null;

		$this->tokens_endpoint
			->shouldReceive( 'setup_tokens' )
			->once()
			->withArgs( function ( PaymentSource $source, string $customer_id ) use ( &$captured_customer_id ): bool {
				$captured_customer_id = $customer_id;
				return true;
			} )
			->andReturn( new \stdClass() );

		// Override the default stub to verify the exact meta key used.
		expect( 'get_user_meta' )
			->once()
			->with( 7, '_ppcp_target_customer_id', true )
			->andReturn( 'cust-abc123' );

		$this->sut->handle_request();

		$this->assertSame( 'cust-abc123', $captured_customer_id );
	}

	/**
	 * GIVEN a nonce validation failure
	 * WHEN handle_request() is called
	 * THEN wp_send_json_error() is called with a message array and HTTP 400 status
	 * AND setup_tokens() is never called
	 */
	public function test_nonce_failure_returns_400_error(): void {
		$this->request_data
			->shouldReceive( 'read_request' )
			->once()
			->andThrow( new NonceValidationException( 'Invalid nonce.' ) );

		$this->tokens_endpoint->shouldNotReceive( 'setup_tokens' );

		expect( 'wp_send_json_error' )
			->once()
			->with(
				Mockery::on( function ( $arg ): bool {
					return is_array( $arg ) && isset( $arg['message'] );
				} ),
				400
			);

		$this->sut->handle_request();
	}
}
