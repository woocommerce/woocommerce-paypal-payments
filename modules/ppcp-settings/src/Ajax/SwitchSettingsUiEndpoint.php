<?php
/**
 * The settings UI switching Ajax endpoint.
 *
 * @package WooCommerce\PayPalCommerce\Settings\Endpoint
 */

declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\Settings\Ajax;

use Exception;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\Button\Endpoint\RequestData;
use WooCommerce\PayPalCommerce\Settings\Data\OnboardingProfile;

/**
 * Class SwitchSettingsUiEndpoint
 *
 * Note: This is an ajax handler, not a REST endpoint
 */
class SwitchSettingsUiEndpoint {

	public const ENDPOINT                      = 'ppcp-settings-switch-ui';
	public const OPTION_NAME_SHOULD_USE_OLD_UI = 'woocommerce_ppcp-settings-should-use-old-ui';

	/**
	 * The RequestData.
	 *
	 * @var RequestData
	 */
	protected RequestData $request_data;

	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	protected LoggerInterface $logger;

	/**
	 * The Onboarding profile.
	 *
	 * @var OnboardingProfile
	 */
	protected OnboardingProfile $onboarding_profile;

	/**
	 * True if the merchant is onboarded, otherwise false.
	 *
	 * @var bool
	 */
	protected bool $is_onboarded;

	/**
	 * SwitchSettingsUiEndpoint constructor.
	 *
	 * @param LoggerInterface   $logger The logger.
	 * @param RequestData       $request_data The Request data.
	 * @param OnboardingProfile $onboarding_profile The Onboarding profile.
	 * @param bool              $is_onboarded True if the merchant is onboarded, otherwise false.
	 */
	public function __construct(
		LoggerInterface $logger,
		RequestData $request_data,
		OnboardingProfile $onboarding_profile,
		bool $is_onboarded
	) {
		$this->logger             = $logger;
		$this->request_data       = $request_data;
		$this->onboarding_profile = $onboarding_profile;
		$this->is_onboarded       = $is_onboarded;
	}

	/**
	 * Handles the request.
	 */
	public function handle_request(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'Not an admin.', 403 );
			return;
		}

		try {
			$this->request_data->read_request( $this->nonce() );
			update_option( self::OPTION_NAME_SHOULD_USE_OLD_UI, 'no' );

			if ( $this->is_onboarded ) {
				$this->onboarding_profile->set_completed( true );
				$this->onboarding_profile->save();
			}

			wp_send_json_success();
		} catch ( Exception $error ) {
			wp_send_json_error( array( 'message' => $error->getMessage() ), 500 );
		}
	}

	/**
	 * The nonce.
	 *
	 * @return string
	 */
	public static function nonce(): string {
		return self::ENDPOINT;
	}
}
