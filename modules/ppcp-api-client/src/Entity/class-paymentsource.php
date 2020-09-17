<?php
/**
 * The PaymentSource object.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Entity
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Entity;

/**
 * Class PaymentSource
 */
class PaymentSource {

	/**
	 * The card.
	 *
	 * @var PaymentSourceCard|null
	 */
	private $card;

	/**
	 * The wallet.
	 *
	 * @var PaymentSourceWallet|null
	 */
	private $wallet;

	/**
	 * PaymentSource constructor.
	 *
	 * @param PaymentSourceCard|null   $card The card.
	 * @param PaymentSourceWallet|null $wallet The wallet.
	 */
	public function __construct(
		PaymentSourceCard $card = null,
		PaymentSourceWallet $wallet = null
	) {

		$this->card   = $card;
		$this->wallet = $wallet;
	}

	/**
	 * Returns the card.
	 *
	 * @return PaymentSourceCard|null
	 */
	public function card() {

		return $this->card;
	}

	/**
	 * Returns the wallet.
	 *
	 * @return PaymentSourceWallet|null
	 */
	public function wallet() {

		return $this->wallet;
	}

	/**
	 * Returns the array of the object.
	 *
	 * @return array
	 */
	public function to_array(): array {

		$data = array();
		if ( $this->card() ) {
			$data['card'] = $this->card()->to_array();
		}
		if ( $this->wallet() ) {
			$data['wallet'] = $this->wallet()->to_array();
		}
		return $data;
	}
}
