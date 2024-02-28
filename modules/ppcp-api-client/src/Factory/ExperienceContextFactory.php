<?php
/**
 * The ExperienceContextFactory factory.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Factory
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Factory;

use stdClass;
use WooCommerce\PayPalCommerce\ApiClient\Entity\ExperienceContext;

/**
 * Class ExperienceContextFactory
 */
class ExperienceContextFactory {

	/**
	 * Returns an Application Context based off a PayPal Response.
	 *
	 * @param stdClass $data The JSON object.
	 *
	 * @return ExperienceContext
	 */
	public function from_paypal_response( stdClass $data ): ExperienceContext {
		return new ExperienceContext(
			$data->return_url ?? '',
			$data->cancel_url ?? '',
			$data->brand_name ?? '',
			$data->locale ?? '',
			$data->landing_page ?? ExperienceContext::LANDING_PAGE_NO_PREFERENCE,
			$data->shipping_preference ?? ExperienceContext::SHIPPING_PREFERENCE_GET_FROM_FILE,
			$data->user_action ?? ExperienceContext::USER_ACTION_CONTINUE
		);
	}
}
