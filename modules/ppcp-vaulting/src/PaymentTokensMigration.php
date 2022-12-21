<?php
/**
 * The payment tokens migration handler.
 *
 * @package WooCommerce\PayPalCommerce\Vaulting
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Vaulting;

use Exception;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;

/**
 * Class PaymentTokensMigration
 */
class PaymentTokensMigration {

	/**
	 * WC Payment token ACDC (Advanced Credit and Debit Card).
	 *
	 * @var PaymentTokenACDC
	 */
	private $payment_token_acdc;

	/**
	 * WC Payment token PayPal.
	 *
	 * @var PaymentTokenPayPal
	 */
	private $payment_token_paypal;

	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * PaymentTokensMigration constructor.
	 *
	 * @param PaymentTokenPayPal $payment_token_paypal WC Payment token PayPal.
	 * @param LoggerInterface    $logger The logger.
	 */
	public function __construct(
		PaymentTokenACDC  $payment_token_acdc,
		PaymentTokenPayPal $payment_token_paypal,
		LoggerInterface $logger
	) {
		$this->payment_token_acdc = $payment_token_acdc;
		$this->payment_token_paypal = $payment_token_paypal;
		$this->logger               = $logger;
	}

	/**
	 * Migrates user existing vaulted tokens into WC payment tokens API.
	 *
	 * @param int $id WooCommerce customer id.
	 */
	public function migrate_payment_tokens_for_user( int $id ):void {
		$tokens          = (array) get_user_meta( $id, PaymentTokenRepository::USER_META, true );
		$tokens_migrated = 0;

		foreach ( $tokens as $token ) {
			if ( isset( $token->source()->card ) ) {
				$this->payment_token_acdc->set_token( $token->id() );
				$this->payment_token_acdc->set_user_id( $id );
				$this->payment_token_acdc->set_gateway_id( CreditCardGateway::ID );
				$this->payment_token_acdc->set_last4( $token->source()->card->last_digits );
				$this->payment_token_acdc->set_card_type( $token->source()->card->brand );

				try {
					$this->payment_token_acdc->save();
				} catch ( Exception $exception ) {
					$this->logger->error(
						"Could not save WC payment token credit card {$token->id()} for user {$id}. "
						. $exception->getMessage()
					);
					continue;
				}

				$tokens_migrated++;

			} elseif ( $token->source()->paypal ) {
				$this->payment_token_paypal->set_token( $token->id() );
				$this->payment_token_paypal->set_user_id( $id );
				$this->payment_token_paypal->set_gateway_id( PayPalGateway::ID );

				$email = $token->source()->paypal->payer->email_address ?? '';
				if ( $email && is_email( $email ) ) {
					$this->payment_token_paypal->set_email( $email );
				}

				try {
					$this->payment_token_paypal->save();
				} catch ( Exception $exception ) {
					$this->logger->error(
						"Could not save WC payment token PayPal {$token->id()} for user {$id}. "
						. $exception->getMessage()
					);
					continue;
				}

				$tokens_migrated++;
			}
		}

		if ( $tokens_migrated > 0 && count( $tokens ) === $tokens_migrated ) {
			update_user_meta( $id, 'ppcp_tokens_migrated', true );
		}
	}
}
