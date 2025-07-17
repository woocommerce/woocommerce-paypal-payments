<?php
/**
 * PayPal Settings Blueprint Step
 *
 * @package WooCommerce\PayPalCommerce\Compat\WooCommerceBlueprint
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Compat\WooCommerceBlueprint;

use Automattic\WooCommerce\Blueprint\Steps\Step;

/**
 * PayPal Settings Step (for custom import handling if needed)
 */
class PayPalSettingsStep extends Step {

	/**
	 * PayPal options data
	 *
	 * @var array<string, mixed>
	 */
	private array $paypal_options;

	/**
	 * Constructor
	 *
	 * @param array<string, mixed> $paypal_options PayPal options data.
	 */
	public function __construct( array $paypal_options ) {
		$this->paypal_options = $paypal_options;
	}

	/**
	 * Get step name
	 *
	 * @return string
	 */
	public static function get_step_name(): string {
		return 'setPayPalOptions';
	}

	/**
	 * Get schema
	 *
	 * @param int $version Schema version.
	 * @return array<string, mixed>
	 */
	public static function get_schema( int $version = 1 ): array {
		return [
			'type'       => 'object',
			'properties' => [
				'step'    => [
					'type' => 'string',
					'enum' => [ static::get_step_name() ],
				],
				'options' => [
					'type'                 => 'object',
					'additionalProperties' => true,
				],
			],
			'required'   => [ 'step', 'options' ],
		];
	}

	/**
	 * Prepare JSON array
	 *
	 * @return array<string, mixed>
	 */
	public function prepare_json_array(): array {
		return [
			'step'    => static::get_step_name(),
			'options' => $this->paypal_options,
		];
	}
}
