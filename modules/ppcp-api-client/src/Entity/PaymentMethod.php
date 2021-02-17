<?php
/**
 * The PaymentMethod object
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Entity
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Entity;

/**
 * Class PaymentMethod
 */
class PaymentMethod {


	const PAYER_SELECTED_DEFAULT = 'PAYPAL';

	const PAYEE_PREFERRED_UNRESTRICTED               = 'UNRESTRICTED';
	const PAYEE_PREFERRED_IMMEDIATE_PAYMENT_REQUIRED = 'IMMEDIATE_PAYMENT_REQUIRED';

	/**
	 * The preferred value.
	 *
	 * @var string
	 */
	private $preferred;

	/**
	 * The selected value.
	 *
	 * @var string
	 */
	private $selected;

	/**
	 * PaymentMethod constructor.
	 *
	 * @param string $preferred The preferred value.
	 * @param string $selected The selected value.
	 */
	public function __construct(
		string $preferred = self::PAYEE_PREFERRED_UNRESTRICTED,
		string $selected = self::PAYER_SELECTED_DEFAULT
	) {

		$this->preferred = $preferred;
		$this->selected  = $selected;
	}

	/**
	 * Returns the payer preferred value.
	 *
	 * @return string
	 */
	public function payee_preferred(): string {
		return $this->preferred;
	}

	/**
	 * Returns the payer selected value.
	 *
	 * @return string
	 */
	public function payer_selected(): string {
		return $this->selected;
	}

	/**
	 * Returns the object as array.
	 *
	 * @return array
	 */
	public function to_array(): array {
		return array(
			'payee_preferred' => $this->payee_preferred(),
			'payer_selected'  => $this->payer_selected(),
		);
	}
}
