<?php
/**
 * Handles the Webhook VAULT.PAYMENT-TOKEN.CREATED
 *
 * @package WooCommerce\PayPalCommerce\Webhooks\Handler
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Webhooks\Handler;

use Psr\Log\LoggerInterface;
use WC_Payment_Token_CC;
use WC_Payment_Tokens;
use WooCommerce\PayPalCommerce\Vaulting\PaymentTokenFactory;
use WooCommerce\PayPalCommerce\Vaulting\PaymentTokenHelper;
use WooCommerce\PayPalCommerce\Vaulting\PaymentTokenPayPal;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcGateway\Processor\AuthorizedPaymentsProcessor;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Class VaultPaymentTokenCreated
 */
class VaultPaymentTokenCreated implements RequestHandler {
	use RequestHandlerTrait;

	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	protected $logger;

	/**
	 * The prefix.
	 *
	 * @var string
	 */
	protected $prefix;

	/**
	 * The authorized payment processor.
	 *
	 * @var AuthorizedPaymentsProcessor
	 */
	protected $authorized_payments_processor;

	/**
	 * The payment token factory.
	 *
	 * @var PaymentTokenFactory
	 */
	protected $payment_token_factory;

	/**
	 * The payment token helper.
	 *
	 * @var PaymentTokenHelper
	 */
	private $payment_token_helper;

	/**
	 * VaultPaymentTokenCreated constructor.
	 *
	 * @param LoggerInterface             $logger The logger.
	 * @param string                      $prefix The prefix.
	 * @param AuthorizedPaymentsProcessor $authorized_payments_processor The authorized payment processor.
	 * @param PaymentTokenFactory         $payment_token_factory The payment token factory.
	 * @param PaymentTokenHelper          $payment_token_helper The payment token helper.
	 */
	public function __construct(
		LoggerInterface $logger,
		string $prefix,
		AuthorizedPaymentsProcessor $authorized_payments_processor,
		PaymentTokenFactory $payment_token_factory,
		PaymentTokenHelper $payment_token_helper
	) {
		$this->logger                        = $logger;
		$this->prefix                        = $prefix;
		$this->authorized_payments_processor = $authorized_payments_processor;
		$this->payment_token_factory         = $payment_token_factory;
		$this->payment_token_helper          = $payment_token_helper;
	}

	/**
	 * The event types a handler handles.
	 *
	 * @return string[]
	 */
	public function event_types(): array {
		return array(
			'VAULT.PAYMENT-TOKEN.CREATED',
		);
	}

	/**
	 * Whether a handler is responsible for a given request or not.
	 *
	 * @param WP_REST_Request $request The request.
	 *
	 * @return bool
	 */
	public function responsible_for_request( WP_REST_Request $request ): bool {
		return in_array( $request['event_type'], $this->event_types(), true );
	}

	/**
	 * Responsible for handling the request.
	 *
	 * @param WP_REST_Request $request The request.
	 *
	 * @return WP_REST_Response
	 */
	public function handle_request( WP_REST_Request $request ): WP_REST_Response {
		$customer_id = null !== $request['resource'] && isset( $request['resource']['customer_id'] )
			? $request['resource']['customer_id']
			: '';

		if ( ! $customer_id ) {
			$message = 'No customer id was found.';
			return $this->failure_response( $message );
		}

		$wc_customer_id = $this->wc_customer_id_from( $customer_id );
		if ( ! $wc_customer_id ) {
			$message = "No WC customer id was found from PayPal customer id {$customer_id}";
			return $this->failure_response( $message );
		}

		$this->authorized_payments_processor->capture_authorized_payments_for_customer( $wc_customer_id );

		if ( ! is_null( $request['resource'] ) && isset( $request['resource']['id'] ) ) {
			if ( ! is_null( $request['resource']['source'] ) && isset( $request['resource']['source']['card'] ) ) {
				$wc_tokens = WC_Payment_Tokens::get_customer_tokens( $wc_customer_id, CreditCardGateway::ID );
				if ( ! $this->payment_token_helper->token_exist( $wc_tokens, $request['resource']['id'] ) ) {
					$token = new WC_Payment_Token_CC();
					$token->set_token( $request['resource']['id'] );
					$token->set_user_id( $wc_customer_id );
					$token->set_gateway_id( CreditCardGateway::ID );

					$token->set_last4( $request['resource']['source']['card']['last_digits'] ?? '' );
					$expiry = explode( '-', $request['resource']['source']['card']['expiry'] ?? '' );
					$token->set_expiry_year( $expiry[0] ?? '' );
					$token->set_expiry_month( $expiry[1] ?? '' );
					$token->set_card_type( $request['resource']['source']['card']['brand'] ?? '' );
					$token->save();
					WC_Payment_Tokens::set_users_default( $wc_customer_id, $token->get_id() );
				}
			} elseif ( isset( $request['resource']['source']['paypal'] ) ) {
				$wc_tokens = WC_Payment_Tokens::get_customer_tokens( $wc_customer_id, PayPalGateway::ID );
				if ( ! $this->payment_token_helper->token_exist( $wc_tokens, $request['resource']['id'] ) ) {
					$payment_token_paypal = $this->payment_token_factory->create( 'paypal' );
					assert( $payment_token_paypal instanceof PaymentTokenPayPal );

					$payment_token_paypal->set_token( $request['resource']['id'] );
					$payment_token_paypal->set_user_id( $wc_customer_id );
					$payment_token_paypal->set_gateway_id( PayPalGateway::ID );

					$email = $request['resource']['source']['paypal']['payer']['email_address'] ?? '';
					if ( $email && is_email( $email ) ) {
						$payment_token_paypal->set_email( $email );
					}

					$payment_token_paypal->save();
					WC_Payment_Tokens::set_users_default( $wc_customer_id, $payment_token_paypal->get_id() );
				}
			}
		}

		return $this->success_response();
	}

	/**
	 * Returns WC customer id from PayPal customer id.
	 *
	 * @param string $customer_id The customer ID from PayPal.
	 * @return int
	 */
	private function wc_customer_id_from( string $customer_id ): int {
		$customers = get_users(
			array(
				'meta_key'   => 'ppcp_guest_customer_id', //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => $customer_id, //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'fields'     => 'ids',
				'number'     => 1,
			)
		);

		$wc_customer_id = $customers[0] ?? '';
		if ( $wc_customer_id ) {
			return (int) $wc_customer_id;
		}

		$id = str_replace( $this->prefix, '', $customer_id );
		return is_numeric( $id ) ? (int) $id : 0;
	}
}
