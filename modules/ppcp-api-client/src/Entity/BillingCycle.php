<?php
/**
 * The Billing Cycle object.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Entity
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Entity;

class BillingCycle {

	/**
	 * @var array
	 */
	private $frequency;

	/**
	 * @var int
	 */
	private $sequence;

	/**
	 * @var string
	 */
	private $tenure_type;

	/**
	 * @var array
	 */
	private $pricing_scheme;

	/**
	 * @var int
	 */
	private $total_cycles;

	public function __construct(
		array $frequency,
		int $sequence,
		string $tenure_type,
		array $pricing_scheme = array(),
		int $total_cycles = 1
	) {
		$this->frequency      = $frequency;
		$this->sequence       = $sequence;
		$this->tenure_type    = $tenure_type;
		$this->pricing_scheme = $pricing_scheme;
		$this->total_cycles   = $total_cycles;
	}

	/**
	 * @return array
	 */
	public function frequency(): array {
		return $this->frequency;
	}

	/**
	 * @return int
	 */
	public function sequence(): int {
		return $this->sequence;
	}

	/**
	 * @return string
	 */
	public function tenure_type(): string {
		return $this->tenure_type;
	}

	/**
	 * @return array
	 */
	public function pricing_scheme(): array {
		return $this->pricing_scheme;
	}

	/**
	 * @return int
	 */
	public function total_cycles(): int {
		return $this->total_cycles;
	}

	/**
	 * Returns Billing Cycle as array.
	 *
	 * @return array
	 */
	public function to_array(): array {
		return array(
			'frequency' => $this->frequency(),
			'sequence' => $this->sequence(),
			'tenure_type' => $this->tenure_type(),
			'pricing_scheme' => $this->pricing_scheme(),
			'total_cycles' => $this->total_cycles(),
		);
	}
}
