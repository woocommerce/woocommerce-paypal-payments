<?php
/**
 * Create payment token for guest user.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Endpoint
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\SavePaymentMethods\Endpoint;

use Exception;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PaymentMethodTokensEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentSource;
use WooCommerce\PayPalCommerce\Button\Endpoint\EndpointInterface;
use WooCommerce\PayPalCommerce\OrderEndpoints\Endpoint\RequestData;
use WooCommerce\PayPalCommerce\Button\Exception\NonceValidationException;
use WooCommerce\PayPalCommerce\SavePaymentMethods\Service\SetupTokenBinding;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\FreeTrialSubscriptionHelper;

/**
 * Class UpdateCustomerId
 */
class CreatePaymentTokenForGuest implements EndpointInterface {

	const ENDPOINT = 'ppc-update-customer-id';

	/**
	 * The request data.
	 *
	 * @var RequestData
	 */
	private $request_data;

	/**
	 * The payment method tokens endpoint.
	 *
	 * @var PaymentMethodTokensEndpoint
	 */
	private $payment_method_tokens_endpoint;

	private SetupTokenBinding $setup_token_binding;

	private FreeTrialSubscriptionHelper $free_trial_helper;

	private SessionHandler $session_handler;

	private LoggerInterface $logger;

	public function __construct(
		RequestData $request_data,
		PaymentMethodTokensEndpoint $payment_method_tokens_endpoint,
		SetupTokenBinding $setup_token_binding,
		FreeTrialSubscriptionHelper $free_trial_helper,
		SessionHandler $session_handler,
		LoggerInterface $logger
	) {
		$this->request_data                   = $request_data;
		$this->payment_method_tokens_endpoint = $payment_method_tokens_endpoint;
		$this->setup_token_binding            = $setup_token_binding;
		$this->free_trial_helper              = $free_trial_helper;
		$this->session_handler                = $session_handler;
		$this->logger                         = $logger;
	}

	/**
	 * Returns the nonce.
	 *
	 * @return string
	 */
	public static function nonce(): string {
		return self::ENDPOINT;
	}

	/**
	 * Handles the request.
	 *
	 * @throws Exception On Error.
	 */
	public function handle_request(): void {
		try {
			$data = $this->request_data->read_request( $this->nonce() );
		} catch ( NonceValidationException $error ) {
			wp_send_json_error( array( 'message' => $error->getMessage() ), 400 );
		}

		$setup_token_id = is_string( $data['vault_setup_token'] ?? null ) ? $data['vault_setup_token'] : '';
		if ( ! $this->setup_token_binding->consume( $setup_token_id ) ) {
			$this->logger->warning( 'Rejected a guest setup token exchange: the token was not created in the current session.' );
			wp_send_json_error( null, 403 );
		}

		/**
		 * Suppress ArgumentTypeCoercion
		 *
		 * @psalm-suppress ArgumentTypeCoercion
		 */
		$payment_source = new PaymentSource(
			'token',
			(object) array(
				'id'   => $setup_token_id,
				'type' => 'SETUP_TOKEN',
			)
		);

		try {
			$payment_token = $this->payment_method_tokens_endpoint->create_payment_token( $payment_source );
		} catch ( Exception $exception ) {
			$this->logger->error( 'Could not exchange a guest setup token: ' . $exception->getMessage() );
			wp_send_json_error();
		}

		WC()->session->set( 'ppcp_guest_payment_for_free_trial', $payment_token );

		// Only the Blocks express buttons send free_trial_continuation; card
		// vaults call this endpoint without it.
		$is_free_trial_continuation = ! empty( $data['free_trial_continuation'] );
		if (
			$is_free_trial_continuation
			&& isset( $payment_token->payment_source->paypal )
			&& $this->free_trial_helper->is_free_trial_cart()
		) {
			$this->session_handler->mark_free_trial_vaulted_with_paypal();
		}

		wp_send_json_success();
	}
}
