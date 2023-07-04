<?php
/**
 * The payment p24 (Przelewy24) object.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentSource
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentSource;

use WooCommerce\PayPalCommerce\ApiClient\Entity\ExperienceContext;

/**
 * Class P24
 */
class P24 implements PaymentSourceInterface {
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
	 * The email of the payer.
	 *
	 * @var string
	 */
	private $email;

	/**
	 * The ExperienceContext.
	 *
	 * @var ExperienceContext|null
	 */
	private $experience_context;

	/**
	 * P24 constructor.
	 *
	 * @param string                 $name The payer's full name.
	 * @param string                 $country_code The 2-letter country code of the payer.
	 * @param string                 $email The email of the payer.
	 * @param ExperienceContext|null $experience_context The ExperienceContext.
	 */
	public function __construct(
		string $name,
		string $country_code,
		string $email,
		?ExperienceContext $experience_context = null
	) {
		$this->name               = $name;
		$this->country_code       = $country_code;
		$this->email              = $email;
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
	 * Returns the email of the payer.
	 */
	public function email(): string {
		return $this->email;
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
		return 'p24';
	}

	/**
	 * Returns the object as array.
	 */
	public function to_array(): array {
		$data = array(
			'name'         => $this->name,
			'country_code' => $this->country_code,
			'email'        => $this->email,
		);
		if ( $this->experience_context ) {
			$data['experience_context'] = $this->experience_context->to_array();
		}
		return $data;
	}
}
