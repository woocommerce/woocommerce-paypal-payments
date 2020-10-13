<?php
/**
 * Handles the login seller incoming request to receive the credentials from Paypal.
 *
 * @package WooCommerce\PayPalCommerce\Onboarding\Endpoint
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Onboarding\Endpoint;

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
	 * LoginSellerEndpoint constructor.
	 *
	 * @param RequestData          $request_data The Request Data.
	 * @param LoginSeller          $login_seller_production The Login Seller for the production environment.
	 * @param LoginSeller          $login_seller_sandbox The Login Seller for the sandbox environment.
	 * @param PartnerReferralsData $partner_referrals_data The Partner Referrals Data.
	 * @param Settings             $settings The Settings.
	 * @param Cache                $cache The Cache.
	 */
	public function __construct(
		RequestData $request_data,
		LoginSeller $login_seller_production,
		LoginSeller $login_seller_sandbox,
		PartnerReferralsData $partner_referrals_data,
		Settings $settings,
		Cache $cache
	) {

		$this->request_data            = $request_data;
		$this->login_seller_production = $login_seller_production;
		$this->login_seller_sandbox    = $login_seller_sandbox;
		$this->partner_referrals_data  = $partner_referrals_data;
		$this->settings                = $settings;
		$this->cache                   = $cache;
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
			$data       = $this->request_data->read_request( $this->nonce() );
			$is_sandbox = isset( $data['env'] ) && 'sandbox' === $data['env'];
			$this->settings->set( 'sandbox_on', $is_sandbox );
			$this->settings->set( 'products_dcc_enabled', null );
			$this->settings->persist();
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
			if ( $this->cache->has( PayPalBearer::CACHE_KEY ) ) {
				$this->cache->delete( PayPalBearer::CACHE_KEY );
			}
			wp_schedule_single_event(
				time() - 1,
				WebhookRegistrar::EVENT_HOOK
			);
			wp_send_json_success();
			return true;
		} catch ( \RuntimeException $error ) {
			wp_send_json_error( $error->getMessage() );
			return false;
		}
	}
}
