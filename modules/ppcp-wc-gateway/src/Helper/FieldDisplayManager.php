<?php
/**
 * Helper to manage the field display behaviour.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Helper;
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Helper;

/**
 * FieldsManager class.
 */
class FieldDisplayManager {

	/**
	 * The rules.
	 *
	 * @var array
	 */
	protected $rules = array();

	/**
	 * Creates and returns a rule.
	 *
	 * @param string|null $key The rule key.
	 * @return FieldDisplayRule
	 */
	public function rule( string $key = null ): FieldDisplayRule {
		if ( null === $key ) {
			$key = (string) count( $this->rules );
		}

		$rule = new FieldDisplayRule( $key );

		$this->rules[ $key ] = $rule;
		return $rule;
	}

}
