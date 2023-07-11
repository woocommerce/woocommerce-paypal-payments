<?php
/**
 * The payment source trustly object.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentSource
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentSource;

use WooCommerce\PayPalCommerce\ApiClient\Entity\ExperienceContext;

/**
 * Class Trustly
 */
class Trustly implements PaymentSourceInterface {
	/**
	 * The payer's full name.
	 *
	 * @var string
	 */
	private $name;

	/**
	 * The 2-letter country code of the payer.
	 *
	 * @var string
	 */
	private $country_code;

	/**
	 * The ExperienceContext.
	 *
	 * @var ExperienceContext|null
	 */
	private $experience_context;

	/**
	 * Trustly constructor.
	 *
	 * @param string                 $name The payer's full name.
	 * @param string                 $country_code The 2-letter country code of the payer.
	 * @param ExperienceContext|null $experience_context The ExperienceContext.
	 */
	public function __construct(
		string $name,
		string $country_code,
		?ExperienceContext $experience_context = null
	) {
		$this->name               = $name;
		$this->country_code       = $country_code;
		$this->experience_context = $experience_context;
	}

	/**
	 * Returns the payer's full name.
	 */
	public function name(): string {
		return $this->name;
	}

	/**
	 * Returns the 2-letter country code of the payer.
	 */
	public function country_code(): string {
		return $this->country_code;
	}

	/**
	 * Returns the ExperienceContext.
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
		return 'trustly';
	}

	/**
	 * Returns the object as array.
	 */
	public function to_array(): array {
		$data = array(
			'name'         => $this->name,
			'country_code' => $this->country_code,
		);
		if ( $this->experience_context ) {
			$data['experience_context'] = $this->experience_context->to_array();
		}
		return $data;
	}
}
