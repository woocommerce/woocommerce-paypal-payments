<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Onboarding\Endpoint;

use Inpsyde\PayPalCommerce\ApiClient\Authentication\PayPalBearer;
use Inpsyde\PayPalCommerce\ApiClient\Endpoint\LoginSeller;
use Inpsyde\PayPalCommerce\ApiClient\Repository\PartnerReferralsData;
use Inpsyde\PayPalCommerce\Button\Endpoint\EndpointInterface;
use Inpsyde\PayPalCommerce\Button\Endpoint\RequestData;
use Inpsyde\PayPalCommerce\WcGateway\Gateway\WcGatewayInterface;
use Inpsyde\PayPalCommerce\WcGateway\Settings\Settings;
use Inpsyde\PayPalCommerce\Webhooks\WebhookRegistrar;
use Psr\SimpleCache\CacheInterface;

class LoginSellerEndpoint implements EndpointInterface {

	public const ENDPOINT = 'ppc-login-seller';

	private $requestData;
	private $loginSellerEndpoint;
	private $partnerReferralsData;
	private $settings;
	private $cache;
	public function __construct(
		RequestData $requestData,
		LoginSeller $loginSellerEndpoint,
		PartnerReferralsData $partnerReferralsData,
		Settings $settings,
		CacheInterface $cache
	) {

		$this->requestData          = $requestData;
		$this->loginSellerEndpoint  = $loginSellerEndpoint;
		$this->partnerReferralsData = $partnerReferralsData;
		$this->settings             = $settings;
		$this->cache                = $cache;
	}

	public static function nonce(): string {
		return self::ENDPOINT;
	}

	public function handleRequest(): bool {

		try {
			$data        = $this->requestData->readRequest( $this->nonce() );
			$credentials = $this->loginSellerEndpoint->credentialsFor(
				$data['sharedId'],
				$data['authCode'],
				$this->partnerReferralsData->nonce()
			);
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
