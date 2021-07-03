<?php
/**
 * The ApplicationContext factory.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Factory
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Factory;

use WooCommerce\PayPalCommerce\ApiClient\Entity\ApplicationContext;

/**
 * Class ApplicationContextFactory
 */
class ApplicationContextFactory {

	/**
	 * Returns an Application Context based off a PayPal Response.
	 *
	 * @param \stdClass $data The JSON object.
	 *
	 * @return ApplicationContext
	 */
	public function from_paypal_response( \stdClass $data ): ApplicationContext {
		return new ApplicationContext(
			isset( $data->return_url ) ?
				$data->return_url : '',
			isset( $data->cancel_url ) ?
				$data->cancel_url : '',
			isset( $data->brand_name ) ?
				$data->brand_name : '',
			isset( $data->locale ) ?
				$data->locale : '',
			isset( $data->landing_page ) ?
				$data->landing_page : ApplicationContext::LANDING_PAGE_NO_PREFERENCE,
			isset( $data->shipping_preference ) ?
				$data->shipping_preference : ApplicationContext::SHIPPING_PREFERENCE_GET_FROM_FILE,
			isset( $data->user_action ) ?
				$data->user_action : ApplicationContext::USER_ACTION_CONTINUE
		);
	}
}
