<?php
/**
 * Element used by field display manager.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Helper;
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Helper;

/**
 * FieldDisplayRule class.
 */
class FieldDisplayRule {

	/**
	 * The element selector.
	 *
	 * @var string
	 */
	protected $key;

	/**
	 * The conditions of this rule.
	 *
	 * @var array
	 */
	protected $conditions = array();

	/**
	 * The actions of this rule.
	 *
	 * @var array
	 */
	protected $actions = array();

	/**
	 * The actions of this rule.
	 *
	 * @var array
	 */
	protected $add_selector_prefixes = true;

	/**
	 * FieldDisplayElement constructor.
	 *
	 * @param string $key The rule key.
	 */
	public function __construct( string $key ) {
		$this->key = $key;
	}

	/**
	 * Adds a condition to the rule.
	 *
	 * @param string $selector The condition selector.
	 * @param string $operation The condition operation (ex: equals, differs, in, not_empty, empty).
	 * @param mixed  $value The value to compare against.
	 * @return self
	 */
	public function condition( string $selector, string $operation, $value ): self {
		if ( $this->add_selector_prefixes ) {
			$selector = '#ppcp-' . $selector; // Refers to the input.
		}
		$this->conditions[] = array(
			'selector'  => $selector,
			'operation' => $operation,
			'value'     => $value,
		);
		return $this;
	}

	/**
	 * Adds a condition to enable the element.
	 *
	 * @param string $selector The condition selector.
	 * @param string $action The action.
	 */
	public function action( string $selector, string $action ): self {
		if ( $this->add_selector_prefixes ) {
			$selector = '#field-' . $selector; // Refers to the whole field.
		}
		$this->actions[] = array(
			'selector' => $selector,
			'action'   => $action,
		);
		return $this;
	}

	/**
	 * Set if selector prefixes like, "#ppcp-" or "#field-" should be added to condition or action selectors.
	 *
	 * @param bool $add_selector_prefixes If should add prefixes.
	 * @return self
	 */
	public function should_add_selector_prefixes( bool $add_selector_prefixes = true ): self {
		$this->add_selector_prefixes = $add_selector_prefixes;
		return $this;
	}

	/**
	 * Returns array representation.
	 *
	 * @return array
	 */
	public function to_array(): array {
		return array(
			'key'        => $this->key,
			'conditions' => $this->conditions,
			'actions'    => $this->actions,
		);
	}

	/**
	 * Returns JSON representation.
	 *
	 * @return string
	 */
	public function json(): string {
		return wp_json_encode( $this->to_array() ) ?: '';
	}

}
