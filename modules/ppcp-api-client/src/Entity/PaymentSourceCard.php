<?php
/**
 * The PaymentSourceCard object.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Entity
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Entity;

/**
 * Class PaymentSourceCard
 */
class PaymentSourceCard {

	/**
	 * The last digits of the card.
	 *
	 * @var string
	 */
	private $last_digits;

	/**
	 * The brand.
	 *
	 * @var string
	 */
	private $brand;

	/**
	 * The type.
	 *
	 * @var string
	 */
	private $type;

	/**
	 * The authentication result.
	 *
	 * @var CardAuthenticationResult|null
	 */
	private $authentication_result;

	/**
	 * PaymentSourceCard constructor.
	 *
	 * @param string                        $last_digits The last digits of the card.
	 * @param string                        $brand The brand of the card.
	 * @param string                        $type The type of the card.
	 * @param CardAuthenticationResult|null $authentication_result The authentication result.
	 */
	public function __construct(
		string $last_digits,
		string $brand,
		string $type,
		CardAuthenticationResult $authentication_result = null
	) {

		$this->last_digits           = $last_digits;
		$this->brand                 = $brand;
		$this->type                  = $type;
		$this->authentication_result = $authentication_result;
	}

	/**
	 * Returns the last digits.
	 *
	 * @return string
	 */
	public function last_digits(): string {

		return $this->last_digits;
	}

	/**
	 * Returns the brand.
	 *
	 * @return string
	 */
	public function brand(): string {

		return $this->brand;
	}

	/**
	 * Returns the type.
	 *
	 * @return string
	 */
	public function type(): string {

		return $this->type;
	}

	/**
	 * Returns the authentication result.
	 *
	 * @return CardAuthenticationResult|null
	 */
	public function authentication_result() {

		return $this->authentication_result;
	}

	/**
	 * Returns the object as array.
	 *
	 * @return array
	 */
	public function to_array(): array {

		$data = array(
			'last_digits' => $this->last_digits(),
			'brand'       => $this->brand(),
			'type'        => $this->type(),
		);
		if ( $this->authentication_result() ) {
			$data['authentication_result'] = $this->authentication_result()->to_array();
		}
		return $data;
	}
}
