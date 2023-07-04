<?php
/**
 * The payment source paypal object.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentSource
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentSource;

use WooCommerce\PayPalCommerce\ApiClient\Entity\ExperienceContext;

/**
 * Class PayPal
 */
class PayPal implements PaymentSourceInterface {
	/**
	 * The ExperienceContext.
	 *
	 * @var ExperienceContext|null
	 */
	private $experience_context;

	/**
	 * PayPal constructor.
	 *
	 * @param ExperienceContext|null $experience_context The ExperienceContext.
	 */
	public function __construct( ?ExperienceContext $experience_context = null ) {
		$this->experience_context = $experience_context;
	}

	/**
	 * The ExperienceContext.
	 *
	 * @return ExperienceContext|null
	 */
	public function experience_context(): ?ExperienceContext {
		return $this->experience_context;
	}

	/**
	 * Returns the payment source ID.
	 */
	public function payment_source_id(): string {
		return 'paypal';
	}

	/**
	 * Returns the object as array.
	 */
	public function to_array(): array {
		$data = array();
		if ( $this->experience_context ) {
			$data['experience_context'] = $this->experience_context->to_array();
		}
		return $data;
	}
}
