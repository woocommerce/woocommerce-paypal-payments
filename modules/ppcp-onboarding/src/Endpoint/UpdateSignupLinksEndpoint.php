<?php
/**
 * Handles the onboard with Pay upon Invoice setting.
 *
 * @package WooCommerce\PayPalCommerce\Onboarding\Endpoint
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Onboarding\Endpoint;

use Exception;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Helper\Cache;
use WooCommerce\PayPalCommerce\Button\Endpoint\EndpointInterface;
use WooCommerce\PayPalCommerce\Button\Endpoint\RequestData;
use WooCommerce\PayPalCommerce\Onboarding\Helper\OnboardingUrl;
use WooCommerce\PayPalCommerce\Onboarding\Render\OnboardingRenderer;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
use WooCommerce\PayPalCommerce\WcGateway\Exception\NotFoundException;

/**
 * Class UpdateSignupLinksEndpoint
 */
class UpdateSignupLinksEndpoint implements EndpointInterface {

	const ENDPOINT = 'ppc-update-signup-links';

	/**
	 * The settings.
	 *
	 * @var Settings
	 */
	protected $settings;

	/**
	 * The request data.
	 *
	 * @var RequestData
	 */
	protected $request_data;

	/**
	 * The signup link cache.
	 *
	 * @var Cache
	 */
	protected $signup_link_cache;

	/**
	 * The onboarding renderer.
	 *
	 * @var OnboardingRenderer
	 */
	protected $onboarding_renderer;

	/**
	 * Signup link ids.
	 *
	 * @var array
	 */
	protected $signup_link_ids;

	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	protected $logger;

	/**
	 * UpdateSignupLinksEndpoint constructor.
	 *
	 * @param Settings           $settings The settings.
	 * @param RequestData        $request_data The request data.
	 * @param Cache              $signup_link_cache The signup link cache.
	 * @param OnboardingRenderer $onboarding_renderer The onboarding renderer.
	 * @param array              $signup_link_ids Signup link ids.
	 * @param LoggerInterface    $logger The logger.
	 */
	public function __construct(
		Settings $settings,
		RequestData $request_data,
		Cache $signup_link_cache,
		OnboardingRenderer $onboarding_renderer,
		array $signup_link_ids,
		LoggerInterface $logger
	) {
		$this->settings            = $settings;
		$this->request_data        = $request_data;
		$this->signup_link_cache   = $signup_link_cache;
		$this->onboarding_renderer = $onboarding_renderer;
		$this->logger              = $logger;
		$this->signup_link_ids     = $signup_link_ids;
	}

	/**
	 * The nonce.
	 *
	 * @return string
	 */
	public static function nonce(): string {
		return self::ENDPOINT;
	}

	/**
	 * Handles the request.
	 *
	 * @return bool
	 * @throws NotFoundException When order not found or handling failed.
	 */
	public function handle_request(): bool {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'Not admin.', 403 );
			return false;
		}

		$signup_links = array();

		try {
			$data = $this->request_data->read_request( $this->nonce() );

			foreach ( $data['settings'] ?? array() as $field => $value ) {
				$option = apply_filters(
					'ppcp_partner_referrals_option',
					array(
						'field' => $field,
						'value' => $value,
						'valid' => false,
					)
				);

				if ( $option['valid'] ) {
					$this->settings->set( $field, $value );
				}
			}

			$this->settings->persist();

			foreach ( $this->signup_link_ids as $key ) {
				( new OnboardingUrl( $this->signup_link_cache, $key, get_current_user_id() ) )->delete();
			}

			foreach ( $this->signup_link_ids as $key ) {
				$parts                = explode( '-', $key );
				$is_production        = 'production' === $parts[0];
				$products             = 'ppcp' === $parts[1] ? array( 'PPCP' ) : array( 'EXPRESS_CHECKOUT' );
				$signup_links[ $key ] = $this->onboarding_renderer->get_signup_link( $is_production, $products );
			}
		} catch ( Exception $exception ) {
			$this->logger->error( $exception->getMessage() );
		}

		wp_send_json_success(
			array(
				'onboarding_pui' => $this->settings->get( 'ppcp-onboarding-pui' ),
				'signup_links'   => $signup_links,
			)
		);

		return true;
	}
}

