<?php
/**
 * Element used by field display manager.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Helper;
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Helper;

use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;

/**
 * DisplayRule class.
 */
class DisplayRule {

	const CONDITION_TYPE_ELEMENT = 'element';
	const CONDITION_TYPE_BOOL    = 'bool';

	const CONDITION_OPERATION_EQUALS     = 'equals';
	const CONDITION_OPERATION_NOT_EQUALS = 'not_equals';
	const CONDITION_OPERATION_IN         = 'in';
	const CONDITION_OPERATION_NOT_IN     = 'not_in';
	const CONDITION_OPERATION_EMPTY      = 'empty';
	const CONDITION_OPERATION_NOT_EMPTY  = 'not_empty';

	const ACTION_TYPE_ELEMENT = 'element';

	const ACTION_VISIBLE = 'visible';
	const ACTION_ENABLE  = 'enable';

	/**
	 * The element selector.
	 *
	 * @var string
	 */
	protected $key;

	/**
	 * The settings.
	 *
	 * @var Settings
	 */
	private $settings;

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
	 * Indicates if this class should add selector prefixes.
	 *
	 * @var bool
	 */
	protected $add_selector_prefixes = true;

	/**
	 * FieldDisplayElement constructor.
	 *
	 * @param string   $key The rule key.
	 * @param Settings $settings The settings.
	 */
	public function __construct( string $key, Settings $settings ) {
		$this->key      = $key;
		$this->settings = $settings;
	}

	/**
	 * Adds a condition related to an HTML element.
	 *
	 * @param string $selector The condition selector.
	 * @param mixed  $value The value to compare against.
	 * @param string $operation The condition operation (ex: equals, differs, in, not_empty, empty).
	 * @return self
	 */
	public function condition_element( string $selector, $value, string $operation = self::CONDITION_OPERATION_EQUALS ): self {
		$this->add_condition(
			array(
				'type'      => self::CONDITION_TYPE_ELEMENT,
				'selector'  => $selector,
				'operation' => $operation,
				'value'     => $value,
			)
		);
		return $this;
	}

	/**
	 * Adds a condition related to a bool check.
	 *
	 * @param bool $value The value to enable / disable the condition.
	 * @return self
	 */
	public function condition_is_true( bool $value ): self {
		$this->add_condition(
			array(
				'type'  => self::CONDITION_TYPE_BOOL,
				'value' => $value,
			)
		);
		return $this;
	}

	/**
	 * Adds a condition related to the settings.
	 *
	 * @param string $settings_key The settings key.
	 * @param mixed  $value The value to compare against.
	 * @param string $operation The condition operation (ex: equals, differs, in, not_empty, empty).
	 * @return self
	 */
	public function condition_is_settings( string $settings_key, $value, string $operation = self::CONDITION_OPERATION_EQUALS ): self {
		$settings_value = null;

		if ( $this->settings->has( $settings_key ) ) {
			$settings_value = $this->settings->get( $settings_key );
		}

		$this->condition_is_true( $this->resolve_operation( $settings_value, $value, $operation ) );
		return $this;
	}

	/**
	 * Adds a condition to show/hide the element.
	 *
	 * @param string $selector The condition selector.
	 */
	public function action_visible( string $selector ): self {
		$this->add_action(
			array(
				'type'     => self::ACTION_TYPE_ELEMENT,
				'selector' => $selector,
				'action'   => self::ACTION_VISIBLE,
			)
		);
		return $this;
	}

	/**
	 * Adds a condition to enable/disable the element.
	 *
	 * @param string $selector The condition selector.
	 */
	public function action_enable( string $selector ): self {
		$this->add_action(
			array(
				'type'     => self::ACTION_TYPE_ELEMENT,
				'selector' => $selector,
				'action'   => self::ACTION_ENABLE,
			)
		);
		return $this;
	}

	/**
	 * Adds a condition to the rule.
	 *
	 * @param array $options The condition options.
	 * @return void
	 */
	private function add_condition( array $options ): void {
		if ( $this->add_selector_prefixes && isset( $options['selector'] ) ) {
			$options['selector'] = '#ppcp-' . $options['selector']; // Refers to the input.
		}

		if ( ! isset( $options['key'] ) ) {
			$options['key'] = '_condition_' . ( (string) count( $this->conditions ) );
		}

		$this->conditions[] = $options;
	}

	/**
	 * Adds an action to do.
	 *
	 * @param array $options The action options.
	 * @return void
	 */
	private function add_action( array $options ): void {
		if ( $this->add_selector_prefixes && isset( $options['selector'] ) ) {
			$options['selector'] = '#field-' . $options['selector']; // Refers to the whole field.
		}

		if ( ! isset( $options['key'] ) ) {
			$options['key'] = '_action_' . ( (string) count( $this->actions ) );
		}

		$this->actions[] = $options;
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
	 * Adds a condition related to the settings.
	 *
	 * @param mixed  $value_1 The value 1.
	 * @param mixed  $value_2 The value 2.
	 * @param string $operation The condition operation (ex: equals, differs, in, not_empty, empty).
	 * @return bool
	 */
	private function resolve_operation( $value_1, $value_2, string $operation ): bool {
		switch ( $operation ) {
			case self::CONDITION_OPERATION_EQUALS:
				return $value_1 === $value_2;
			case self::CONDITION_OPERATION_NOT_EQUALS:
				return $value_1 !== $value_2;
			case self::CONDITION_OPERATION_IN:
				return in_array( $value_1, $value_2, true );
			case self::CONDITION_OPERATION_NOT_IN:
				return ! in_array( $value_1, $value_2, true );
			case self::CONDITION_OPERATION_EMPTY:
				return empty( $value_1 );
			case self::CONDITION_OPERATION_NOT_EMPTY:
				return ! empty( $value_1 );
		}
		return false;
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
