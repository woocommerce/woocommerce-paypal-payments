<?php
/**
 * Resolution option for validation issues.
 *
 * Provides factory methods to create standardized resolution options
 * that suggest actions to resolve validation issues.
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\Schema
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Schema;

use WooCommerce\PayPalCommerce\StoreSync\Enums\Priority;
use WooCommerce\PayPalCommerce\StoreSync\Enums\ResolutionAction;

/**
 * Immutable resolution option builder with factory methods.
 */
class ResolutionOption {
	private string $action;
	private string $label;
	private string $url;
	private array $metadata;

	/**
	 * Private constructor to enforce factory method usage.
	 *
	 * @param string $action   Resolution action constant from ResolutionAction.
	 * @param string $label    Human-readable action description.
	 * @param string $url      Optional. URL for redirect actions.
	 * @param array  $metadata Optional. Additional metadata.
	 */
	private function __construct( string $action, string $label, string $url = '', array $metadata = array() ) {
		$this->action   = $action;
		$this->label    = $label;
		$this->url      = $url;
		$this->metadata = $metadata;
	}

	/**
	 * Creates a new instance with additional properties.
	 *
	 * Allows chaining to customize the resolution option.
	 *
	 * @param array $props Properties to merge (label, url, metadata).
	 * @return self New instance with merged properties.
	 */
	public function with( array $props ): self {
		$label    = $props['label'] ?? $this->label;
		$url      = $props['url'] ?? $this->url;
		$metadata = array_merge( $this->metadata, $props['metadata'] ?? array() );

		return new self( $this->action, $label, $url, $metadata );
	}

	/**
	 * Converts to array for JSON serialization.
	 *
	 * @return array Resolution option data.
	 */
	public function to_array(): array {
		$data = array(
			'action' => $this->action,
			'label'  => $this->label,
		);

		if ( $this->url ) {
			$validated_url = \wp_validate_redirect( $this->url, '' );
			if ( $validated_url ) {
				$data['url'] = $validated_url;
			}
		}

		if ( ! empty( $this->metadata ) ) {
			$data['metadata'] = $this->metadata;
		}

		return $data;
	}

	/**
	 * Factory: Remove item from cart.
	 *
	 * @param string $priority Optional. Priority level from Priority class constants.
	 * @param array  $metadata Optional. Additional metadata.
	 * @return self
	 */
	public static function remove_item( string $priority = Priority::MEDIUM, array $metadata = array() ): self {
		$metadata['priority'] = $priority;

		return new self(
			ResolutionAction::REMOVE_ITEM,
			'Remove from cart',
			'',
			$metadata
		);
	}

	/**
	 * Factory: Update shipping address.
	 *
	 * @param string $label    Optional. Custom label.
	 * @param string $priority Optional. Priority level from Priority class constants.
	 * @param array  $metadata Optional. Additional metadata.
	 * @return self
	 */
	public static function update_address( string $label = 'Update shipping address', string $priority = Priority::MEDIUM, array $metadata = array() ): self {
		$metadata['priority'] = $priority;

		return new self(
			ResolutionAction::UPDATE_ADDRESS,
			$label,
			'',
			$metadata
		);
	}

	/**
	 * Factory: Modify cart (e.g., reduce quantity).
	 *
	 * @param string $label    Action description.
	 * @param array  $metadata Optional. Additional metadata (e.g., max_quantity).
	 * @return self
	 */
	public static function modify_cart( string $label, array $metadata = array() ): self {
		return new self(
			ResolutionAction::MODIFY_CART,
			$label,
			'',
			$metadata
		);
	}

	/**
	 * Factory: Suggest alternative product.
	 *
	 * @param string $label    Optional. Custom label.
	 * @param array  $metadata Optional. Additional metadata.
	 * @return self
	 */
	public static function suggest_alternative( string $label = 'View similar items', array $metadata = array() ): self {
		return new self(
			ResolutionAction::SUGGEST_ALTERNATIVE,
			$label,
			'',
			$metadata
		);
	}

	/**
	 * Factory: Provide missing field.
	 *
	 * @param string $field_name The field that needs to be provided.
	 * @param string $label      Optional. Custom label.
	 * @param array  $metadata   Optional. Additional metadata.
	 * @return self
	 */
	public static function provide_missing_field( string $field_name, string $label = '', array $metadata = array() ): self {
		if ( ! $label ) {
			$label = sprintf( 'Provide %s', $field_name );
		}

		$metadata['field'] = $field_name;

		return new self(
			ResolutionAction::PROVIDE_MISSING_FIELD,
			$label,
			'',
			$metadata
		);
	}

	public static function provide_shipping_address(): self {
		return self::provide_missing_field( 'shipping_address', 'Add shipping address' )->with(
			array( 'metadata' => array( 'priority' => Priority::HIGH ) )
		);
	}

	/**
	 * Factory: Wait for product restock.
	 *
	 * @param string $label    Optional. Custom label.
	 * @param array  $metadata Optional. Additional metadata.
	 * @return self
	 */
	public static function wait_for_restock( string $label = 'Wait for restock', array $metadata = array() ): self {
		return new self(
			ResolutionAction::WAIT_FOR_RESTOCK,
			$label,
			'',
			$metadata
		);
	}

	/**
	 * Factory: Use different currency.
	 *
	 * @param string $label            Action description.
	 * @param string $expected_currency The expected currency code.
	 * @param array  $metadata          Optional. Additional metadata.
	 * @return self
	 */
	public static function use_different_currency( string $label, string $expected_currency, array $metadata = array() ): self {
		$metadata['expected_currency'] = $expected_currency;

		return new self(
			ResolutionAction::USE_DIFFERENT_CURRENCY,
			$label,
			'',
			$metadata
		);
	}

	/**
	 * Factory: Accept new price.
	 *
	 * @param string $label    Action description.
	 * @param array  $metadata Optional. Additional metadata (e.g., cost_impact).
	 * @return self
	 */
	public static function accept_new_price( string $label, array $metadata = array() ): self {
		return new self(
			ResolutionAction::ACCEPT_NEW_PRICE,
			$label,
			'',
			$metadata
		);
	}

	/**
	 * Factory: Redirect to merchant site.
	 *
	 * @param string $label    Action description.
	 * @param string $url      Redirect URL.
	 * @param array  $metadata Optional. Additional metadata.
	 * @return self
	 */
	public static function redirect_to_merchant( string $label, string $url, array $metadata = array() ): self {
		return new self(
			ResolutionAction::REDIRECT_TO_MERCHANT,
			$label,
			$url,
			$metadata
		);
	}

	/**
	 * Factory: Update shipping method.
	 *
	 * @param string $label    Action description.
	 * @param array  $metadata Optional. Additional metadata.
	 * @return self
	 */
	public static function update_shipping_method( string $label, array $metadata = array() ): self {
		return new self(
			ResolutionAction::UPDATE_SHIPPING_METHOD,
			$label,
			'',
			$metadata
		);
	}

	/**
	 * Factory: Contact support.
	 *
	 * @param string $label    Optional. Custom label.
	 * @param array  $metadata Optional. Additional metadata.
	 * @return self
	 */
	public static function contact_support( string $label = 'Contact support', array $metadata = array() ): self {
		return new self(
			ResolutionAction::CONTACT_SUPPORT,
			$label,
			'',
			$metadata
		);
	}

	/**
	 * Factory: Remove coupon from cart.
	 *
	 * @param string $label    Optional. Custom label.
	 * @param string $priority Optional. Priority level from Priority class constants.
	 * @param array  $metadata Optional. Additional metadata.
	 * @return self
	 */
	public static function remove_coupon( string $label = 'Continue without coupon', string $priority = Priority::MEDIUM, array $metadata = array() ): self {
		$metadata['priority'] = $priority;

		return new self(
			ResolutionAction::REMOVE_COUPON,
			$label,
			'',
			$metadata
		);
	}

	/**
	 * Factory: Apply a different coupon.
	 *
	 * @param string $label    Action description.
	 * @param string $priority Optional. Priority level from Priority class constants.
	 * @param array  $metadata Optional. Additional metadata.
	 * @return self
	 */
	public static function apply_different_coupon( string $label, string $priority = Priority::MEDIUM, array $metadata = array() ): self {
		$metadata['priority'] = $priority;

		return new self(
			ResolutionAction::APPLY_DIFFERENT_COUPON,
			$label,
			'',
			$metadata
		);
	}

	/**
	 * Factory: Keep current coupon (for stacking conflicts).
	 *
	 * @param string $label    Action description.
	 * @param string $priority Optional. Priority level from Priority class constants.
	 * @param array  $metadata Optional. Additional metadata.
	 * @return self
	 */
	public static function keep_current_coupon( string $label, string $priority = Priority::HIGH, array $metadata = array() ): self {
		$metadata['priority'] = $priority;

		return new self(
			ResolutionAction::KEEP_CURRENT_COUPON,
			$label,
			'',
			$metadata
		);
	}
}
