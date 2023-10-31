<?php
/**
 * Handles the login seller incoming request to receive the credentials from Paypal.
 *
 * @package WooCommerce\PayPalCommerce\Onboarding\Endpoint
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Onboarding\Endpoint;

use Exception;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\PayPalBearer;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\LoginSeller;
use WooCommerce\PayPalCommerce\ApiClient\Helper\Cache;
use WooCommerce\PayPalCommerce\ApiClient\Repository\PartnerReferralsData;
use WooCommerce\PayPalCommerce\Button\Endpoint\EndpointInterface;
use WooCommerce\PayPalCommerce\Button\Endpoint\RequestData;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
use WooCommerce\PayPalCommerce\Webhooks\WebhookRegistrar;

/**
 * Class LoginSellerEndpoint
 */
class LoginSellerEndpoint implements EndpointInterface {

	const ENDPOINT = 'ppc-login-seller';

	/**
	 * The Request Data helper object.
	 *
	 * @var RequestData
	 */
	private $request_data;

	/**
	 * The Login Seller Endpoint for the production environment
	 *
	 * @var LoginSeller
	 */
	private $login_seller_production;

	/**
	 * The Login Seller Endpoint for the sandbox environment
	 *
	 * @var LoginSeller
	 */
	private $login_seller_sandbox;

	/**
	 * The Partner Referrals Data.
	 *
	 * @var PartnerReferralsData
	 */
	private $partner_referrals_data;

	/**
	 * The Settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * The Cache.
	 *
	 * @var Cache
	 */
	private $cache;

	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	protected $logger;

	/**
	 * LoginSellerEndpoint constructor.
	 *
	 * @param RequestData          $request_data The Request Data.
	 * @param LoginSeller          $login_seller_production The Login Seller for the production environment.
	 * @param LoginSeller          $login_seller_sandbox The Login Seller for the sandbox environment.
	 * @param PartnerReferralsData $partner_referrals_data The Partner Referrals Data.
	 * @param Settings             $settings The Settings.
	 * @param Cache                $cache The Cache.
	 * @param LoggerInterface      $logger The logger.
	 */
	public function __construct(
		RequestData $request_data,
		LoginSeller $login_seller_production,
		LoginSeller $login_seller_sandbox,
		PartnerReferralsData $partner_referrals_data,
		Settings $settings,
		Cache $cache,
		LoggerInterface $logger
	) {

		$this->request_data            = $request_data;
		$this->login_seller_production = $login_seller_production;
		$this->login_seller_sandbox    = $login_seller_sandbox;
		$this->partner_referrals_data  = $partner_referrals_data;
		$this->settings                = $settings;
		$this->cache                   = $cache;
		$this->logger                  = $logger;
	}

	/**
	 * Returns the nonce for the endpoint.
	 *
	 * @return string
	 */
	public static function nonce(): string {
		return self::ENDPOINT;
	}

	/**
	 * Handles the incoming request.
	 *
	 * @return bool
	 */
	public function handle_request(): bool {

		try {
			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				wp_send_json_error( 'Not admin.', 403 );
				return false;
			}

			$data       = $this->request_data->read_request( $this->nonce() );
			$is_sandbox = isset( $data['env'] ) && 'sandbox' === $data['env'];
			$this->settings->set( 'sandbox_on', $is_sandbox );
			$this->settings->set( 'products_dcc_enabled', null );
			$this->settings->set( 'products_pui_enabled', null );
			$this->settings->persist();
			do_action( 'woocommerce_paypal_payments_clear_apm_product_status', $this->settings );

			$endpoint    = $is_sandbox ? $this->login_seller_sandbox : $this->login_seller_production;
			$credentials = $endpoint->credentials_for(
				$data['sharedId'],
				$data['authCode'],
				$this->partner_referrals_data->nonce()
			);
			if ( $is_sandbox ) {
				$this->settings->set( 'client_secret_sandbox', $credentials->client_secret );
				$this->settings->set( 'client_id_sandbox', $credentials->client_id );
			} else {
				$this->settings->set( 'client_secret_production', $credentials->client_secret );
				$this->settings->set( 'client_id_production', $credentials->client_id );
			}
			$this->settings->set( 'client_secret', $credentials->client_secret );
			$this->settings->set( 'client_id', $credentials->client_id );
			$this->settings->persist();

			$accept_cards    = (bool) ( $data['acceptCards'] ?? true );
			$funding_sources = array();
			if ( $this->settings->has( 'disable_funding' ) ) {
				$funding_sources = $this->settings->get( 'disable_funding' );
				if ( ! is_array( $funding_sources ) ) {
					$funding_sources = array();
				}
			}
			if ( $accept_cards ) {
				$funding_sources = array_diff( $funding_sources, array( 'card' ) );
			} else {
				if ( ! in_array( 'card', $funding_sources, true ) ) {
					$funding_sources[] = 'card';
				}
			}
			$this->settings->set( 'disable_funding', $funding_sources );

			$this->settings->persist();

			if ( $this->cache->has( PayPalBearer::CACHE_KEY ) ) {
				$this->cache->delete( PayPalBearer::CACHE_KEY );
			}

			wp_schedule_single_event(
				time() + 5,
				WebhookRegistrar::EVENT_HOOK
			);
			wp_send_json_success();
			return true;
		} catch ( Exception $error ) {
			$this->logger->error( 'Onboarding completion handling error: ' . $error->getMessage() );
			wp_send_json_error( $error->getMessage() );
			return false;
		}
	}
}
