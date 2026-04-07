<?php
declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\Tests\Integration\Vaulting;

use Mockery;
use Psr\Log\LoggerInterface;
use WC_Payment_Token_CC;
use WC_Payment_Tokens;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PaymentTokensEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentSource;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\Tests\Integration\IntegrationMockedTestCase;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcPaymentTokens\PaymentTokenApplePay;
use WooCommerce\PayPalCommerce\WcPaymentTokens\PaymentTokenPayPal;
use WooCommerce\PayPalCommerce\WcPaymentTokens\PaymentTokenVenmo;
use WooCommerce\PayPalCommerce\WcPaymentTokens\WooCommercePaymentTokens;

class WooCommercePaymentTokensTest extends IntegrationMockedTestCase {
	private WooCommercePaymentTokens $sut;
	private PaymentTokensEndpoint $mock_endpoint;
	private LoggerInterface $mock_logger;

	public function setUp(): void {
		parent::setUp();

		$this->mock_endpoint = Mockery::mock( PaymentTokensEndpoint::class );
		$this->mock_logger   = Mockery::mock( LoggerInterface::class )->shouldIgnoreMissing();

		// Bootstrap module to register woocommerce_payment_token_class filter.
		$this->bootstrapModule( [
			'api.endpoint.payment-tokens' => function () {
				return $this->mock_endpoint;
			},
		] );

		$this->sut = new WooCommercePaymentTokens(
			$this->mock_endpoint,
			$this->mock_logger
		);
	}

	public function tearDown(): void {
		$this->delete_all_tokens_for_customer( $this->customer_id );
		delete_user_meta( $this->customer_id, '_ppcp_target_customer_id' );
		delete_user_meta( $this->customer_id, 'ppcp_customer_id' );

		parent::tearDown();
	}

	public function test_create_payment_token_paypal(): void {
		$id = $this->sut->create_payment_token_paypal( $this->customer_id, 'tok_pp_1', 'buyer@paypal.com' );

		$this->assertGreaterThan( 0, $id );

		$token = WC_Payment_Tokens::get( $id );
		$this->assertInstanceOf( PaymentTokenPayPal::class, $token );
		$this->assertSame( 'tok_pp_1', $token->get_token() );
		$this->assertSame( $this->customer_id, $token->get_user_id() );
		$this->assertSame( PayPalGateway::ID, $token->get_gateway_id() );
		$this->assertSame( 'buyer@paypal.com', $token->get_email() );
	}

	public function test_create_payment_token_paypal_prevents_duplicate(): void {
		$id1 = $this->sut->create_payment_token_paypal( $this->customer_id, 'tok_dup', 'a@b.com' );
		$id2 = $this->sut->create_payment_token_paypal( $this->customer_id, 'tok_dup', 'a@b.com' );

		$this->assertGreaterThan( 0, $id1 );
		$this->assertSame( 0, $id2 );

		$tokens        = WC_Payment_Tokens::get_customer_tokens( $this->customer_id, PayPalGateway::ID );
		$paypal_tokens = array_filter( $tokens, function ( $t ) {
			return $t instanceof PaymentTokenPayPal;
		} );
		$this->assertCount( 1, $paypal_tokens );
	}

	public function test_create_payment_token_venmo(): void {
		$id = $this->sut->create_payment_token_venmo( $this->customer_id, 'tok_venmo_1', 'buyer@venmo.com' );

		$this->assertGreaterThan( 0, $id );

		$token = WC_Payment_Tokens::get( $id );
		$this->assertInstanceOf( PaymentTokenVenmo::class, $token );
		$this->assertSame( 'tok_venmo_1', $token->get_token() );
		$this->assertSame( $this->customer_id, $token->get_user_id() );
		$this->assertSame( PayPalGateway::ID, $token->get_gateway_id() );
		$this->assertSame( 'buyer@venmo.com', $token->get_email() );
	}

	public function test_create_payment_token_applepay(): void {
		$id = $this->sut->create_payment_token_applepay( $this->customer_id, 'tok_apple_1' );

		$this->assertGreaterThan( 0, $id );

		$token = WC_Payment_Tokens::get( $id );
		$this->assertInstanceOf( PaymentTokenApplePay::class, $token );
		$this->assertSame( 'tok_apple_1', $token->get_token() );
		$this->assertSame( $this->customer_id, $token->get_user_id() );
		$this->assertSame( PayPalGateway::ID, $token->get_gateway_id() );
	}

	public function test_create_payment_token_card(): void {
		$payment_token = (object) [
			'id'             => 'card_tok_1',
			'payment_source' => (object) [
				'card' => (object) [
					'last_digits' => '4242',
					'expiry'      => '2028-06',
					'brand'       => 'VISA',
				],
			],
		];

		$id = $this->sut->create_payment_token_card( $this->customer_id, $payment_token );

		$this->assertGreaterThan( 0, $id );

		$token = WC_Payment_Tokens::get( $id );
		$this->assertInstanceOf( WC_Payment_Token_CC::class, $token );
		$this->assertSame( 'card_tok_1', $token->get_token() );
		$this->assertSame( $this->customer_id, $token->get_user_id() );
		$this->assertSame( CreditCardGateway::ID, $token->get_gateway_id() );
		$this->assertSame( '4242', $token->get_last4() );
		$this->assertSame( '2028', $token->get_expiry_year() );
		$this->assertSame( '06', $token->get_expiry_month() );
		$this->assertSame( 'VISA', $token->get_card_type() );
	}

	public function test_create_payment_token_card_prevents_duplicate(): void {
		$payment_token = (object) [
			'id'             => 'card_dup',
			'payment_source' => (object) [
				'card' => (object) [
					'last_digits' => '1111',
					'expiry'      => '2030-12',
					'brand'       => 'MASTERCARD',
				],
			],
		];

		$id1 = $this->sut->create_payment_token_card( $this->customer_id, $payment_token );
		$id2 = $this->sut->create_payment_token_card( $this->customer_id, $payment_token );

		$this->assertGreaterThan( 0, $id1 );
		$this->assertSame( 0, $id2 );
	}

	public function test_customer_tokens_uses_correct_customer_id_meta(): void {
		// Primary meta key _ppcp_target_customer_id takes precedence.
		update_user_meta( $this->customer_id, '_ppcp_target_customer_id', 'CUST-TARGET' );
		update_user_meta( $this->customer_id, 'ppcp_customer_id', 'CUST-LEGACY' );

		$this->mock_endpoint
			->shouldReceive( 'payment_tokens_for_customer' )
			->once()
			->with( 'CUST-TARGET' )
			->andReturn( [ [ 'id' => 'tok_1', 'payment_source' => new PaymentSource( 'paypal', (object) [] ) ] ] );

		$result = $this->sut->customer_tokens( $this->customer_id );
		$this->assertCount( 1, $result );

		// Fallback to legacy meta when primary is absent.
		delete_user_meta( $this->customer_id, '_ppcp_target_customer_id' );

		$this->mock_endpoint
			->shouldReceive( 'payment_tokens_for_customer' )
			->once()
			->with( 'CUST-LEGACY' )
			->andReturn( [ [ 'id' => 'tok_2', 'payment_source' => new PaymentSource( 'card', (object) [] ) ] ] );

		$result = $this->sut->customer_tokens( $this->customer_id );
		$this->assertCount( 1, $result );
		$this->assertSame( 'tok_2', $result[0]['id'] );
	}

	public function test_customer_tokens_returns_empty_on_api_failure(): void {
		update_user_meta( $this->customer_id, '_ppcp_target_customer_id', 'CUST-FAIL' );

		$this->mock_endpoint
			->shouldReceive( 'payment_tokens_for_customer' )
			->once()
			->andThrow( new RuntimeException( 'API error' ) );

		$result = $this->sut->customer_tokens( $this->customer_id );
		$this->assertSame( [], $result );
	}

	public function test_create_wc_tokens_creates_tokens_from_mixed_sources(): void {
		$customer_tokens = [
			[
				'id'             => 'tok_pp_mixed',
				'payment_source' => new PaymentSource( 'paypal', (object) [ 'email_address' => 'mixed@paypal.com' ] ),
			],
			[
				'id'             => 'tok_card_mixed',
				'payment_source' => new PaymentSource( 'card', (object) [
					'last_digits' => '5678',
					'brand'       => 'AMEX',
					'expiry'      => '2029-03',
				] ),
			],
		];

		$this->sut->create_wc_tokens( $customer_tokens, $this->customer_id );

		$paypal_tokens = WC_Payment_Tokens::get_customer_tokens( $this->customer_id, PayPalGateway::ID );
		$paypal_found  = false;
		foreach ( $paypal_tokens as $t ) {
			if ( $t instanceof PaymentTokenPayPal && $t->get_token() === 'tok_pp_mixed' ) {
				$paypal_found = true;
				$this->assertSame( 'mixed@paypal.com', $t->get_email() );
			}
		}
		$this->assertTrue( $paypal_found, 'PayPal token should be created from paypal source' );

		$card_tokens = WC_Payment_Tokens::get_customer_tokens( $this->customer_id, CreditCardGateway::ID );
		$card_found  = false;
		foreach ( $card_tokens as $t ) {
			if ( $t instanceof WC_Payment_Token_CC && $t->get_token() === 'tok_card_mixed' ) {
				$card_found = true;
				$this->assertSame( '5678', $t->get_last4() );
				$this->assertSame( 'AMEX', $t->get_card_type() );
			}
		}
		$this->assertTrue( $card_found, 'Card token should be created from card source' );
	}

	private function delete_all_tokens_for_customer( int $customer_id ): void {
		$tokens = array_merge(
			WC_Payment_Tokens::get_customer_tokens( $customer_id, PayPalGateway::ID ),
			WC_Payment_Tokens::get_customer_tokens( $customer_id, CreditCardGateway::ID )
		);

		foreach ( $tokens as $token ) {
			$token->delete( true );
		}
	}
}
