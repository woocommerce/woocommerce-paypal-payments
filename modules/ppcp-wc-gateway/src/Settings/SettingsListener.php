<?php
/**
 * Listens to requests and updates the settings if necessary.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Settings
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Settings;

use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\AdminNotices\Entity\Message;
use WooCommerce\PayPalCommerce\AdminNotices\Repository\Repository;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\Bearer;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\PayPalBearer;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\ApiClient\Helper\Cache;
use WooCommerce\PayPalCommerce\Http\RedirectorInterface;
use WooCommerce\PayPalCommerce\Onboarding\State;
use WooCommerce\PayPalCommerce\Onboarding\Helper\OnboardingUrl;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcGateway\Helper\DCCProductStatus;
use WooCommerce\PayPalCommerce\WcGateway\Helper\PayUponInvoiceProductStatus;
use WooCommerce\PayPalCommerce\Webhooks\WebhookRegistrar;
use WooCommerce\PayPalCommerce\WcGateway\Exception\NotFoundException;
use WooCommerce\WooCommerce\Logging\Logger\NullLogger;

/**
 * Class SettingsListener
 */
class SettingsListener {

	use PageMatcherTrait;

	const NONCE = 'ppcp-settings';

	private const CREDENTIALS_ADDED     = 'credentials_added';
	private const CREDENTIALS_REMOVED   = 'credentials_removed';
	private const CREDENTIALS_CHANGED   = 'credentials_changed';
	private const CREDENTIALS_UNCHANGED = 'credentials_unchanged';

	/**
	 * The Settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Array contains the setting fields.
	 *
	 * @var array
	 */
	private $setting_fields;

	/**
	 * The Webhook Registrar.
	 *
	 * @var WebhookRegistrar
	 */
	private $webhook_registrar;

	/**
	 * The Cache.
	 *
	 * @var Cache
	 */
	private $cache;

	/**
	 * The State.
	 *
	 * @var State
	 */
	private $state;

	/**
	 * The Bearer.
	 *
	 * @var Bearer
	 */
	private $bearer;

	/**
	 * ID of the current PPCP gateway settings page, or empty if it is not such page.
	 *
	 * @var string
	 */
	protected $page_id;

	/**
	 * The signup link cache.
	 *
	 * @var Cache
	 */
	protected $signup_link_cache;

	/**
	 * Signup link ids
	 *
	 * @var array
	 */
	protected $signup_link_ids;

	/**
	 * The PUI status cache.
	 *
	 * @var Cache
	 */
	protected $pui_status_cache;

	/**
	 * The DCC status cache.
	 *
	 * @var Cache
	 */
	protected $dcc_status_cache;

	/**
	 * The HTTP redirector.
	 *
	 * @var RedirectorInterface
	 */
	protected $redirector;

	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * Max onboarding URL retries.
	 *
	 * @var int
	 */
	private $onboarding_max_retries = 5;

	/**
	 * Delay between onboarding URL retries.
	 *
	 * @var int
	 */
	private $onboarding_retry_delay = 2;

	/**
	 * SettingsListener constructor.
	 *
	 * @param Settings            $settings The settings.
	 * @param array               $setting_fields The setting fields.
	 * @param WebhookRegistrar    $webhook_registrar The Webhook Registrar.
	 * @param Cache               $cache The Cache.
	 * @param State               $state The state.
	 * @param Bearer              $bearer The bearer.
	 * @param string              $page_id ID of the current PPCP gateway settings page, or empty if it is not such page.
	 * @param Cache               $signup_link_cache The signup link cache.
	 * @param array               $signup_link_ids Signup link ids.
	 * @param Cache               $pui_status_cache The PUI status cache.
	 * @param Cache               $dcc_status_cache The DCC status cache.
	 * @param RedirectorInterface $redirector The HTTP redirector.
	 * @param ?LoggerInterface    $logger The logger.
	 */
	public function __construct(
		Settings $settings,
		array $setting_fields,
		WebhookRegistrar $webhook_registrar,
		Cache $cache,
		State $state,
		Bearer $bearer,
		string $page_id,
		Cache $signup_link_cache,
		array $signup_link_ids,
		Cache $pui_status_cache,
		Cache $dcc_status_cache,
		RedirectorInterface $redirector,
		LoggerInterface $logger = null
	) {

		$this->settings          = $settings;
		$this->setting_fields    = $setting_fields;
		$this->webhook_registrar = $webhook_registrar;
		$this->cache             = $cache;
		$this->state             = $state;
		$this->bearer            = $bearer;
		$this->page_id           = $page_id;
		$this->signup_link_cache = $signup_link_cache;
		$this->signup_link_ids   = $signup_link_ids;
		$this->pui_status_cache  = $pui_status_cache;
		$this->dcc_status_cache  = $dcc_status_cache;
		$this->redirector        = $redirector;
		$this->logger            = $logger ?: new NullLogger();
	}

	/**
	 * Listens if the merchant ID should be updated.
	 */
	public function listen_for_merchant_id(): void {
		if ( ! $this->is_valid_site_request() ) {
			return;
		}

		/**
		 * No nonce provided.
		 * phpcs:disable WordPress.Security.NonceVerification.Missing
		 * phpcs:disable WordPress.Security.NonceVerification.Recommended
		 */
		if ( ! isset( $_GET['merchantIdInPayPal'] ) || ! isset( $_GET['merchantId'] ) || ! isset( $_GET['ppcpToken'] ) ) {
			return;
		}

		$merchant_id      = sanitize_text_field( wp_unslash( $_GET['merchantIdInPayPal'] ) );
		$merchant_email   = sanitize_text_field( wp_unslash( $_GET['merchantId'] ) );
		$onboarding_token = sanitize_text_field( wp_unslash( $_GET['ppcpToken'] ) );
		$retry_count      = isset( $_GET['ppcpRetry'] ) ? ( (int) sanitize_text_field( wp_unslash( $_GET['ppcpRetry'] ) ) ) : 0;
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$this->settings->set( 'merchant_id', $merchant_id );
		$this->settings->set( 'merchant_email', $merchant_email );

		// If no client_id is present we will try to wait for PayPal to invoke LoginSellerEndpoint.
		if ( ! $this->settings->has( 'client_id' ) || ! $this->settings->get( 'client_id' ) ) {

			// Try at most {onboarding_max_retries} times ({onboarding_retry_delay} seconds delay). Then give up and just fill the merchant fields like before.
			if ( $retry_count < $this->onboarding_max_retries ) {

				if ( $this->onboarding_retry_delay > 0 ) {
					sleep( $this->onboarding_retry_delay );
				}

				$retry_count++;
				$this->logger->info( 'Retrying onboarding return URL, retry nr: ' . ( (string) $retry_count ) );
				$redirect_url = add_query_arg( 'ppcpRetry', $retry_count );
				$this->redirector->redirect( $redirect_url );
			}
		}

		// Process token validation.
		$onboarding_token_sample = ( (string) substr( $onboarding_token, 0, 2 ) ) . '...' . ( (string) substr( $onboarding_token, -6 ) );
		$this->logger->debug( 'Validating onboarding ppcpToken: ' . $onboarding_token_sample );

		if ( ! OnboardingUrl::validate_token_and_delete( $this->signup_link_cache, $onboarding_token, get_current_user_id() ) ) {
			if ( OnboardingUrl::validate_previous_token( $this->signup_link_cache, $onboarding_token, get_current_user_id() ) ) {
				// It's a valid token used previously, don't do anything but silently redirect.
				$this->logger->info( 'Validated previous token, silently redirecting: ' . $onboarding_token_sample );
				$this->onboarding_redirect();
			} else {
				$this->logger->error( 'Failed to validate onboarding ppcpToken: ' . $onboarding_token_sample );
				$this->onboarding_redirect( false );
			}
		}

		$this->logger->info( 'Validated onboarding ppcpToken: ' . $onboarding_token_sample );

		// Save the merchant data.
		$is_sandbox = $this->settings->has( 'sandbox_on' ) && $this->settings->get( 'sandbox_on' );
		if ( $is_sandbox ) {
			$this->settings->set( 'merchant_id_sandbox', $merchant_id );
			$this->settings->set( 'merchant_email_sandbox', $merchant_email );
		} else {
			$this->settings->set( 'merchant_id_production', $merchant_id );
			$this->settings->set( 'merchant_email_production', $merchant_email );
		}
		$this->settings->persist();

		/**
		 * The hook fired before performing the redirect at the end of onboarding after saving the merchant ID/email.
		 */
		do_action( 'woocommerce_paypal_payments_onboarding_before_redirect' );

		// If after all the retry redirects there still isn't a valid client_id then just send an error.
		if ( ! $this->settings->has( 'client_id' ) || ! $this->settings->get( 'client_id' ) ) {
			$this->onboarding_redirect( false );
		}

		$this->onboarding_redirect();
	}

	/**
	 * Redirect to the onboarding URL.
	 *
	 * @param bool $success Should redirect to the success or error URL.
	 * @return void
	 */
	private function onboarding_redirect( bool $success = true ): void {
		$redirect_url = $this->get_onboarding_redirect_url();

		if ( ! $success ) {
			$redirect_url = add_query_arg( 'ppcp-onboarding-error', '1', $redirect_url );
			$this->logger->info( 'Redirect ERROR: ' . $redirect_url );
		} else {
			$redirect_url = remove_query_arg( 'ppcp-onboarding-error', $redirect_url );
			$this->logger->info( 'Redirect OK: ' . $redirect_url );
		}

		$this->redirector->redirect( $redirect_url );
	}

	/**
	 * Prevent enabling both Pay Later messaging and PayPal vaulting
	 *
	 * @throws RuntimeException When API request fails.
	 */
	public function listen_for_vaulting_enabled() {
		if ( ! $this->is_valid_site_request() || State::STATE_ONBOARDED !== $this->state->current_state() ) {
			return;
		}

		try {
			$token = $this->bearer->bearer();
			if ( ! $token->vaulting_available() ) {
				$this->settings->set( 'vault_enabled', false );
				$this->settings->set( 'vault_enabled_dcc', false );
				$this->settings->persist();
				return;
			}
		} catch ( RuntimeException $exception ) {
			$this->settings->set( 'vault_enabled', false );
			$this->settings->set( 'vault_enabled_dcc', false );
			$this->settings->persist();

			throw $exception;
		}

		/**
		 * No need to verify nonce here.
		 *
		 * phpcs:disable WordPress.Security.NonceVerification.Missing
		 * phpcs:disable WordPress.Security.NonceVerification.Recommended
		 */
		if ( ! isset( $_POST['ppcp']['vault_enabled'] ) ) {
			return;
		}

		$pay_later_messaging_enabled = $this->settings->has( 'pay_later_messaging_enabled' ) && $this->settings->get( 'pay_later_messaging_enabled' );
		if ( $pay_later_messaging_enabled ) {
			$this->settings->set( 'pay_later_messaging_enabled', false );
			$this->settings->persist();
		}

		$pay_later_button_enabled = $this->settings->has( 'pay_later_button_enabled' ) && $this->settings->get( 'pay_later_button_enabled' );
		if ( $pay_later_button_enabled ) {
			$this->settings->set( 'pay_later_button_enabled', false );
			$this->settings->persist();
		}
	}

	/**
	 * Listens to the request.
	 *
	 * @throws \WooCommerce\PayPalCommerce\WcGateway\Exception\NotFoundException When a setting was not found.
	 */
	public function listen(): void {

		if ( ! $this->is_valid_update_request() ) {
			return;
		}

		/**
		 * Sanitization is done in retrieve_settings_from_raw_data().
		 *
		 * phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		 *
		 * Nonce verification is done in is_valid_update_request().
		 *
		 * phpcs:disable WordPress.Security.NonceVerification.Missing
		 * phpcs:disable WordPress.Security.NonceVerification.Recommended
		 */
		$raw_data = ( isset( $_POST['ppcp'] ) ) ? (array) wp_unslash( $_POST['ppcp'] ) : array();
		// phpcs:enable phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$settings = $this->retrieve_settings_from_raw_data( $raw_data );

		$settings = $this->read_active_credentials_from_settings( $settings );

		$credentials_change_status = null; // Cannot detect on Card Processing page.

		if ( Settings::CONNECTION_TAB_ID === $this->page_id ) {
			$credentials_change_status = $this->determine_credentials_change_status( $settings );
		}

		if ( PayPalGateway::ID === $this->page_id ) {
			$settings['enabled'] = isset( $_POST['woocommerce_ppcp-gateway_enabled'] )
				&& 1 === absint( $_POST['woocommerce_ppcp-gateway_enabled'] );
		}

		// phpcs:enable phpcs:disable WordPress.Security.NonceVerification.Missing
		// phpcs:enable phpcs:disable WordPress.Security.NonceVerification.Missing
		if ( $credentials_change_status ) {
			if ( self::CREDENTIALS_UNCHANGED !== $credentials_change_status ) {
				$this->settings->set( 'products_dcc_enabled', null );
				$this->settings->set( 'products_pui_enabled', null );
			}

			if ( in_array(
				$credentials_change_status,
				array( self::CREDENTIALS_REMOVED, self::CREDENTIALS_CHANGED ),
				true
			) ) {
				$this->webhook_registrar->unregister();

				foreach ( $this->signup_link_ids as $key ) {
					if ( $this->signup_link_cache->has( $key ) ) {
						$this->signup_link_cache->delete( $key );
					}
				}
			}
		}

		foreach ( $settings as $id => $value ) {
			$this->settings->set( $id, $value );
		}
		$this->settings->persist();

		if ( $this->cache->has( PayPalBearer::CACHE_KEY ) ) {
			$this->cache->delete( PayPalBearer::CACHE_KEY );
		}

		if ( $this->pui_status_cache->has( PayUponInvoiceProductStatus::PUI_STATUS_CACHE_KEY ) ) {
			$this->pui_status_cache->delete( PayUponInvoiceProductStatus::PUI_STATUS_CACHE_KEY );
		}

		if ( $this->dcc_status_cache->has( DCCProductStatus::DCC_STATUS_CACHE_KEY ) ) {
			$this->dcc_status_cache->delete( DCCProductStatus::DCC_STATUS_CACHE_KEY );
		}

		$ppcp_reference_transaction_enabled = get_transient( 'ppcp_reference_transaction_enabled' ) ?? '';
		if ( $ppcp_reference_transaction_enabled ) {
			delete_transient( 'ppcp_reference_transaction_enabled' );
		}

		$redirect_url = false;
		if ( $credentials_change_status && self::CREDENTIALS_UNCHANGED !== $credentials_change_status ) {
			$redirect_url = $this->get_onboarding_redirect_url();
		}

		if ( isset( $_GET['ppcp-onboarding-error'] ) ) {
			$redirect_url = remove_query_arg( 'ppcp-onboarding-error', $redirect_url );
		}

		if ( $redirect_url ) {
			$this->redirector->redirect( $redirect_url );
		}

		// phpcs:enable WordPress.Security.NonceVerification.Missing
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Returns the URL opened at the end of onboarding.
	 *
	 * @return string
	 */
	private function get_onboarding_redirect_url(): string {
		/**
		 * The URL opened at the end of onboarding after saving the merchant ID/email.
		 */
		return apply_filters( 'woocommerce_paypal_payments_onboarding_redirect_url', admin_url( 'admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway&ppcp-tab=' . Settings::CONNECTION_TAB_ID ) );
	}

	/**
	 * The actual used client credentials are stored in 'client_secret', 'client_id', 'merchant_id' and 'merchant_email'.
	 * This method populates those fields depending on the sandbox status.
	 *
	 * @param array $settings The settings array.
	 *
	 * @return array
	 */
	private function read_active_credentials_from_settings( array $settings ) : array {
		if ( ! isset( $settings['client_id_sandbox'] ) && ! isset( $settings['client_id_production'] ) ) {
			return $settings;
		}
		$is_sandbox                 = isset( $settings['sandbox_on'] ) && $settings['sandbox_on'];
		$settings['client_id']      = $is_sandbox ? $settings['client_id_sandbox'] : $settings['client_id_production'];
		$settings['client_secret']  = $is_sandbox ? $settings['client_secret_sandbox'] : $settings['client_secret_production'];
		$settings['merchant_id']    = $is_sandbox ? $settings['merchant_id_sandbox'] : $settings['merchant_id_production'];
		$settings['merchant_email'] = $is_sandbox ? $settings['merchant_email_sandbox'] : $settings['merchant_email_production'];
		return $settings;
	}

	/**
	 * Checks whether on the credentials changed.
	 *
	 * @param array $new_settings New settings.
	 * @return string One of the CREDENTIALS_ constants.
	 */
	private function determine_credentials_change_status( array $new_settings ): string {
		$current_id     = $this->settings->has( 'client_id' ) ? $this->settings->get( 'client_id' ) : '';
		$current_secret = $this->settings->has( 'client_secret' ) ? $this->settings->get( 'client_secret' ) : '';
		$new_id         = $new_settings['client_id'] ?? '';
		$new_secret     = $new_settings['client_secret'] ?? '';

		$had_credentials       = $current_id && $current_secret;
		$submitted_credentials = $new_id && $new_secret;

		if ( ! $had_credentials && $submitted_credentials ) {
			return self::CREDENTIALS_ADDED;
		}
		if ( $had_credentials ) {
			if ( ! $submitted_credentials ) {
				return self::CREDENTIALS_REMOVED;
			}

			if (
				$current_id !== $new_id
				|| $current_secret !== $new_secret
			) {
				return self::CREDENTIALS_CHANGED;
			}
		}
		return self::CREDENTIALS_UNCHANGED;
	}

	/**
	 * Sanitizes the settings input data and returns a valid settings array.
	 *
	 * @param array $raw_data The Raw data.
	 *
	 * @return array
	 */
	private function retrieve_settings_from_raw_data( array $raw_data ): array {
		/**
		 * Nonce verification has already been done.
		 * phpcs:disable WordPress.Security.NonceVerification.Recommended
		 */
		if ( ! isset( $_GET['section'] ) ) {
			return array();
		}
		$settings = array();
		foreach ( $this->setting_fields as $key => $config ) {
			if ( ! in_array( $this->state->current_state(), $config['screens'], true ) ) {
				continue;
			}
			if ( ! $this->field_matches_page( $config, $this->page_id ) ) {
				continue;
			}
			switch ( $config['type'] ) {
				case 'checkbox':
					$settings[ $key ] = isset( $raw_data[ $key ] );
					break;
				case 'text':
				case 'number':
					$settings[ $key ] = isset( $raw_data[ $key ] ) ? wp_kses_post( $raw_data[ $key ] ) : '';
					break;
				case 'ppcp-password':
				case 'password':
					$settings[ $key ] = $raw_data[ $key ] ?? '';
					break;
				case 'ppcp-multiselect':
					$values         = isset( $raw_data[ $key ] ) ? (array) $raw_data[ $key ] : array();
					$values_to_save = array();
					foreach ( $values as $index => $raw_value ) {
						$value = sanitize_text_field( $raw_value );
						if ( ! in_array( $value, array_keys( $config['options'] ), true ) ) {
							continue;
						}
						$values_to_save[] = $value;
					}
					$settings[ $key ] = $values_to_save;
					break;
				case 'select':
					$options          = array_keys( $config['options'] );
					$settings[ $key ] = isset( $raw_data[ $key ] ) && in_array(
						sanitize_text_field( $raw_data[ $key ] ),
						$options,
						true
					) ? sanitize_text_field( $raw_data[ $key ] ) : null;
					break;
			}
		}

		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		return $settings;
	}

	/**
	 * Evaluates whether the current request is supposed to update the settings.
	 *
	 * @return bool
	 */
	private function is_valid_update_request(): bool {

		if ( ! $this->is_valid_site_request() ) {
			return false;
		}

		if (
			! isset( $_POST['ppcp-nonce'] )
			|| ! wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_POST['ppcp-nonce'] ) ),
				self::NONCE
			)
		) {
			return false;
		}
		return true;
	}

	/**
	 * Whether we are on the settings page and are allowed to be here.
	 *
	 * @return bool
	 */
	private function is_valid_site_request() : bool {

		if ( empty( $this->page_id ) ) {
			return false;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Prevent enabling tracking if it is not enabled for merchant account.
	 *
	 * @throws RuntimeException When API request fails.
	 */
	public function listen_for_tracking_enabled(): void {
		if ( State::STATE_ONBOARDED !== $this->state->current_state() ) {
			return;
		}

		try {
			$token = $this->bearer->bearer();
			if ( ! $token->is_tracking_available() ) {
				$this->settings->set( 'tracking_enabled', false );
				$this->settings->persist();
				return;
			}
		} catch ( RuntimeException $exception ) {
			$this->settings->set( 'tracking_enabled', false );
			$this->settings->persist();

			throw $exception;
		}
	}
}
