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
	 * The payment token factory.
	 *
	 * @var PaymentTokenFactory
	 */
	private $payment_token_factory;

	/**
	 * The payment token repository.
	 *
	 * @var PaymentTokenRepository
	 */
	private $payment_token_repository;

	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * PaymentTokensMigration constructor.
	 *
	 * @param PaymentTokenFactory    $payment_token_factory The payment token factory.
	 * @param PaymentTokenRepository $payment_token_repository The payment token repository.
	 * @param LoggerInterface        $logger The logger.
	 */
	public function __construct(
		PaymentTokenFactory $payment_token_factory,
		PaymentTokenRepository $payment_token_repository,
		LoggerInterface $logger
	) {
		$this->payment_token_factory    = $payment_token_factory;
		$this->payment_token_repository = $payment_token_repository;
		$this->logger                   = $logger;
	}

	/**
	 * Migrates user existing vaulted tokens into WC payment tokens API.
	 *
	 * @param int $id WooCommerce customer id.
	 */
	public function migrate_payment_tokens_for_user( int $id ):void {
		$tokens          = (array) get_user_meta( $id, 'ppcp-vault-token', true );
		$tokens_migrated = 0;

		if ( ! is_main_site() ) {
			$tokens = $this->payment_token_repository->all_for_user_id( $id );
		}

		foreach ( $tokens as $token ) {
			if ( isset( $token->source()->card ) ) {
				$payment_token_acdc = $this->payment_token_factory->create( 'acdc' );
				assert( $payment_token_acdc instanceof PaymentTokenACDC );

				$payment_token_acdc->set_token( $token->id() );
				$payment_token_acdc->set_user_id( $id );
				$payment_token_acdc->set_gateway_id( CreditCardGateway::ID );
				$payment_token_acdc->set_last4( $token->source()->card->last_digits );
				$payment_token_acdc->set_card_type( $token->source()->card->brand );

				try {
					$payment_token_acdc->save();
				} catch ( Exception $exception ) {
					$this->logger->error(
						"Could not save WC payment token credit card {$token->id()} for user {$id}. "
						. $exception->getMessage()
					);
					continue;
				}

				$tokens_migrated++;

			} elseif ( $token->source()->paypal ) {
				$payment_token_paypal = $this->payment_token_factory->create( 'paypal' );
				assert( $payment_token_paypal instanceof PaymentTokenPayPal );

				$payment_token_paypal->set_token( $token->id() );
				$payment_token_paypal->set_user_id( $id );
				$payment_token_paypal->set_gateway_id( PayPalGateway::ID );

				$email = $token->source()->paypal->payer->email_address ?? '';
				if ( $email && is_email( $email ) ) {
					$payment_token_paypal->set_email( $email );
				}

				try {
					$payment_token_paypal->save();
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
