<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Tests\E2e;

use WC_Payment_Token_CC;
use WC_Payment_Tokens;
use WooCommerce\PayPalCommerce\SavePaymentMethods\Helper\RealTimeAccountUpdaterHelper;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;

class RealTimeAccountUpdaterTest extends TestCase
{
	public function tearDown(): void
	{
		$tokens = WC_Payment_Tokens::get_customer_tokens(1, CreditCardGateway::ID);
		foreach ($tokens as $token) {
			$token->delete();
		}

		parent::tearDown();
	}

	public function testUpdateCard()
	{
		$response = (object)[
			'payment_source' => (object)[
				'card' => (object)[
					'last_digits' => '0004',
					'expiry' => '2042-02',
					'brand' => 'VISA',
				]
			]
		];

		$token = $this->createToken();

		(new RealTimeAccountUpdaterHelper())->update_wc_token_from_paypal_response($response, $token);

		$this->assertTrue($token->get_expiry_year() === '2042');
		$this->assertTrue($token->get_expiry_month() === '02');
		$this->assertTrue($token->get_last4() === '0004');
	}

	public function testUpdateOnlyAllowedCards()
	{
		$response = (object)[
			'payment_source' => (object)[
				'card' => (object)[
					'last_digits' => '0004',
					'expiry' => '2042-02',
					'brand' => 'AMEX',
				]
			]
		];

		$token = $this->createToken('AMEX');

		(new RealTimeAccountUpdaterHelper())->update_wc_token_from_paypal_response($response, $token);

		$this->assertTrue($token->get_expiry_year() === '2025');
		$this->assertTrue($token->get_expiry_month() === '01');
		$this->assertTrue($token->get_last4() === '1234');
	}

	/**
	 * @return WC_Payment_Token_CC
	 */
	private function createToken($brand = 'VISA'): \WC_Payment_Token_CC
	{
		$token = new WC_Payment_Token_CC();
		$token->set_token('abc123');
		$token->set_user_id(1);
		$token->set_gateway_id(CreditCardGateway::ID);

		$token->set_last4('1234');
		$token->set_expiry_month('01');
		$token->set_expiry_year('2025');
		$token->set_card_type($brand);

		$token->save();

		return $token;
	}
}
