<?php
/**
 * The Payment Preferences object.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Entity
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Entity;

class PaymentPreferences {
	/**
	 * @var array
	 */
	private $setup_fee;

	/**
	 * @var bool
	 */
	private $auto_bill_outstanding;

	/**
	 * @var string
	 */
	private $setup_fee_failure_action;

	/**
	 * @var int
	 */
	private $payment_failure_threshold;

	public function __construct(
		array $setup_fee,
		bool $auto_bill_outstanding = true,
		string $setup_fee_failure_action = 'CONTINUE',
		int $payment_failure_threshold = 3
	) {

		$this->setup_fee                 = $setup_fee;
		$this->auto_bill_outstanding     = $auto_bill_outstanding;
		$this->setup_fee_failure_action  = $setup_fee_failure_action;
		$this->payment_failure_threshold = $payment_failure_threshold;
	}

	/**
	 * @return array
	 */
	public function setup_fee(): array {
		return $this->setup_fee;
	}

	/**
	 * @return bool
	 */
	public function auto_bill_outstanding(): bool {
		return $this->auto_bill_outstanding;
	}

	/**
	 * @return string
	 */
	public function setup_fee_failure_action(): string {
		return $this->setup_fee_failure_action;
	}

	/**
	 * @return int
	 */
	public function payment_failure_threshold(): int {
		return $this->payment_failure_threshold;
	}

	public function to_array():array {
		return array(
			'setup_fee'                 => $this->setup_fee(),
			'auto_bill_outstanding'     => $this->auto_bill_outstanding(),
			'setup_fee_failure_action'  => $this->setup_fee_failure_action(),
			'payment_failure_threshold' => $this->payment_failure_threshold(),
		);
	}
}
