<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Tests\Integration\Webhooks;

use WC_Payment_Token_CC;
use WC_Payment_Tokens;
use WooCommerce\PayPalCommerce\Tests\Integration\IntegrationMockedTestCase;
use WooCommerce\PayPalCommerce\Webhooks\Handler\VaultPaymentTokenDeleted;
use WP_REST_Request;

class VaultPaymentTokenDeletedTest extends IntegrationMockedTestCase
{
	/**
	 * @var VaultPaymentTokenDeleted
	 */
	private VaultPaymentTokenDeleted $handler;

	public function setUp(): void
	{
		parent::setUp();

		$container = $this->bootstrapModule();
		$logger = $container->get('woocommerce.logger.woocommerce');

		$this->handler = new VaultPaymentTokenDeleted($logger);
	}

	/**
	 * Test that handle_request successfully deletes a payment token.
	 *
	 * @throws \Exception If token creation fails.
	 */
	public function testHandleRequestDeletesPaymentToken(): void
	{
		// Create token using the reusable method from base class
		$payment_token = $this->createAPaymentTokenForTheCustomer();
		$token_id = $payment_token->get_id();
		$token_value = $payment_token->get_token();

		// Verify token exists in database before webhook
		$this->assertNotNull($token_id);
		$this->assertInstanceOf(WC_Payment_Token_CC::class, WC_Payment_Tokens::get($token_id));

		$request = new WP_REST_Request('POST', '/paypal/webhook');
		$request->set_body_params([
			'event_type' => 'VAULT.PAYMENT-TOKEN.DELETED',
			'resource' => [
				'id' => $token_value,
			],
		]);

		$response = $this->handler->handle_request($request);

		$this->assertEquals(200, $response->get_status());

		$deleted_token = WC_Payment_Tokens::get($token_id);
		$this->assertNull($deleted_token, 'Payment token should be deleted from database');
	}

	/**
	 * Test that handle_request returns success when token doesn't exist.
	 */
	public function testHandleRequestReturnsSuccessWhenTokenNotFound(): void
	{
		$non_existent_token = 'non_existent_token_' . uniqid();

		$request = new WP_REST_Request('POST', '/paypal/webhook');
		$request->set_body_params([
			'event_type' => 'VAULT.PAYMENT-TOKEN.DELETED',
			'resource' => [
				'id' => $non_existent_token,
			],
		]);

		$response = $this->handler->handle_request($request);

		$this->assertEquals(200, $response->get_status());
	}

	/**
	 * Test that responsible_for_request correctly identifies its event type.
	 */
	public function testResponsibleForRequestIdentifiesCorrectEventType(): void
	{
		$request = new WP_REST_Request('POST', '/paypal/webhook');
		$request->set_body_params([
			'event_type' => 'VAULT.PAYMENT-TOKEN.DELETED',
		]);

		$this->assertTrue($this->handler->responsible_for_request($request));
	}
}
