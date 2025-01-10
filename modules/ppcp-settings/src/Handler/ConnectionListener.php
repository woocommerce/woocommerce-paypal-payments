<?php
/**
 * Handles connection-requests, that connect the current site to a PayPal
 * merchant account.
 *
 * @package WooCommerce\PayPalCommerce\Settings\Handler
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Settings\Handler;

use Psr\Log\LoggerInterface;
use RuntimeException;
use WooCommerce\PayPalCommerce\Settings\Service\AuthenticationManager;
use WooCommerce\PayPalCommerce\Settings\Service\OnboardingUrlManager;
use WooCommerce\WooCommerce\Logging\Logger\NullLogger;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
use WooCommerce\PayPalCommerce\Http\RedirectorInterface;

/**
 * Provides a listener that handles merchant-connection requests.
 *
 * Those connection requests are made after the merchant logs into their PayPal
 * account (inside the login popup). At the last step, they see a "Return to
 * Store" button.
 * Clicking that button triggers the merchant-connection request.
 */
class ConnectionListener {
	/**
	 * ID of the current settings page; empty if not on a PayPal settings page.
	 *
	 * @var string
	 */
	private string $settings_page_id;

	/**
	 * Access to the onboarding URL manager.
	 *
	 * @var OnboardingUrlManager
	 */
	private OnboardingUrlManager $url_manager;

	/**
	 * Authentication manager service, responsible to update connection details.
	 *
	 * @var AuthenticationManager
	 */
	private AuthenticationManager $authentication_manager;

	/**
	 * A redirector-instance to redirect the merchant after authentication.
	 * ™
	 *
	 * @var RedirectorInterface
	 */
	private RedirectorInterface $redirector;

	/**
	 * Logger instance, mainly used for debugging purposes.
	 *
	 * @var LoggerInterface
	 */
	private LoggerInterface $logger;

	/**
	 * ID of the current user, set by the process() method.
	 *
	 * @var int
	 */
	private int $user_id;

	/**
	 * Prepare the instance.
	 *
	 * @param string                $settings_page_id       Current plugin settings page ID.
	 * @param OnboardingUrlManager  $url_manager            Get OnboardingURL instances.
	 * @param AuthenticationManager $authentication_manager Authentication manager service.
	 * @param RedirectorInterface   $redirector             Redirect-handler.
	 * @param ?LoggerInterface      $logger                 The logger, for debugging purposes.
	 */
	public function __construct(
		string $settings_page_id,
		OnboardingUrlManager $url_manager,
		AuthenticationManager $authentication_manager,
		RedirectorInterface $redirector,
		LoggerInterface $logger = null
	) {
		$this->settings_page_id       = $settings_page_id;
		$this->url_manager            = $url_manager;
		$this->authentication_manager = $authentication_manager;
		$this->redirector             = $redirector;
		$this->logger                 = $logger ?: new NullLogger();

		// Initialize as "guest", the real ID is provided via process().
		$this->user_id = 0;
	}

	/**
	 * Process the request data, and extract connection details, if present.
	 *
	 * @param int   $user_id The current user ID.
	 * @param array $request Request details to process.
	 *
	 * @throws RuntimeException If the merchant ID does not match the ID previously set via OAuth.
	 */
	public function process( int $user_id, array $request ) : void {
		$this->user_id = $user_id;

		if ( ! $this->is_valid_request( $request ) ) {
			return;
		}

		$token = $this->get_token_from_request( $request );
		if ( ! $this->url_manager->validate_token_and_delete( $token, $this->user_id ) ) {
			return;
		}

		$data = $this->extract_data( $request );
		if ( ! $data ) {
			return;
		}

		$this->logger->info( 'Found OAuth merchant data in request', $data );

		try {
			$this->authentication_manager->finish_oauth_authentication( $data );
		} catch ( \Exception $e ) {
			$this->logger->error( 'Failed to complete authentication: ' . $e->getMessage() );
		}

		$this->redirect_after_authentication();
	}

	/**
	 * Determine, if the request details contain connection data that should be
	 * extracted and stored.
	 *
	 * @param array $request Request details to verify.
	 *
	 * @return bool True, if the request contains valid connection details.
	 */
	private function is_valid_request( array $request ) : bool {
		if ( $this->user_id < 1 || ! $this->settings_page_id ) {
			return false;
		}

		if ( ! user_can( $this->user_id, 'manage_woocommerce' ) ) {
			return false;
		}

		$required_params = array(
			'merchantIdInPayPal',
			'merchantId',
			'ppcpToken',
		);

		foreach ( $required_params as $param ) {
			if ( empty( $request[ $param ] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Extract the merchant details (ID & email) from the request details.
	 *
	 * @param array $request The full request details.
	 *
	 * @return array Structured array with 'is_sandbox', 'merchant_id', and 'merchant_email' keys,
	 *               or an empty array on failure.
	 */
	private function extract_data( array $request ) : array {
		$this->logger->info( 'Extracting connection data from request...' );

		$merchant_id    = $this->get_merchant_id_from_request( $request );
		$merchant_email = $this->get_merchant_email_from_request( $request );

		if ( ! $merchant_id || ! $merchant_email ) {
			return array();
		}

		return array(
			'merchant_id'    => $merchant_id,
			'merchant_email' => $merchant_email,
		);
	}

	/**
	 * Redirects the browser page at the end of the authentication flow.
	 *
	 * @return void
	 */
	private function redirect_after_authentication() : void {
		$redirect_url = $this->get_onboarding_redirect_url();

		$this->redirector->redirect( $redirect_url );
	}

	/**
	 * Returns the sanitized connection token from the incoming request.
	 *
	 * @param array $request Full request details.
	 *
	 * @return string The sanitized token, or an empty string.
	 */
	private function get_token_from_request( array $request ) : string {
		return $this->sanitize_string( $request['ppcpToken'] ?? '' );
	}

	/**
	 * Returns the sanitized merchant ID from the incoming request.
	 *
	 * @param array $request Full request details.
	 *
	 * @return string The sanitized merchant ID, or an empty string.
	 */
	private function get_merchant_id_from_request( array $request ) : string {
		return $this->sanitize_string( $request['merchantIdInPayPal'] ?? '' );
	}

	/**
	 * Returns the sanitized merchant email from the incoming request.
	 *
	 * Note that the email is provided via the argument "merchantId", which
	 * looks incorrect at first, but PayPal uses the email address as merchant
	 * IDm and offers a more anonymous ID via the "merchantIdInPayPal" argument.
	 *
	 * @param array $request Full request details.
	 *
	 * @return string The sanitized merchant email, or an empty string.
	 */
	private function get_merchant_email_from_request( array $request ) : string {
		return $this->sanitize_merchant_email( $request['merchantId'] ?? '' );
	}

	/**
	 * Sanitizes a request-argument for processing.
	 *
	 * @param string $value Value from the request argument.
	 *
	 * @return string Sanitized value.
	 */
	private function sanitize_string( string $value ) : string {
		return trim( sanitize_text_field( wp_unslash( $value ) ) );
	}

	/**
	 * Sanitizes the merchant's email address for processing.
	 *
	 * @param string $email The plain email.
	 *
	 * @return string Sanitized email address.
	 */
	private function sanitize_merchant_email( string $email ) : string {
		return sanitize_text_field( str_replace( ' ', '+', $email ) );
	}

	/**
	 * Returns the URL opened at the end of onboarding.
	 *
	 * @return string
	 */
	private function get_onboarding_redirect_url() : string {
		/**
		 * The URL opened at the end of onboarding after saving the merchant ID/email.
		 */
		return apply_filters(
			'woocommerce_paypal_payments_onboarding_redirect_url',
			admin_url( 'admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway&ppcp-tab=' . Settings::CONNECTION_TAB_ID )
		);
	}
}
