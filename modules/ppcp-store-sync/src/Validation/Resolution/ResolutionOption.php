<?php
/**
 * Resolution option for validation issues.
 *
 * Provides factory methods to create standardized resolution options
 * that suggest actions to resolve validation issues.
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\Validation\\Resolution
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Validation\Resolution;

use WooCommerce\PayPalCommerce\StoreSync\Enums\Priority;
use WooCommerce\PayPalCommerce\StoreSync\Enums\ResolutionAction;

/**
 * Immutable resolution option builder with factory methods.
 */
class ResolutionOption {
	private string $action;
	private ?string $label    = null;
	private ?string $url      = null;
	private ?string $priority = null;
	private array $metadata   = array();

	private function __construct( string $action ) {
		$this->action = $action;
	}

	/**
	 * Assign a custom label to the resolution option.
	 */
	public function label( string $label ): self {
		$this->label = $label;

		return $this;
	}

	/**
	 * Assign a new resolution URL to the option.
	 */
	public function url( string $url ): self {
		$this->url = wp_validate_redirect( $url );

		return $this;
	}

	/**
	 * Changes the priority of the resolution option; available options are defined in
	 * the `Priority` enum.
	 */
	public function priority( string $priority ): self {
		$this->priority = $priority;

		return $this;
	}

	/**
	 * Replaces or extends the resolution metadata.
	 */
	public function metadata( array $metadata, bool $replace = false ): self {
		$this->metadata = $replace ? $metadata : array_merge( $this->metadata, $metadata );

		return $this;
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
			$data['url'] = $this->url;
		}
		if ( ! empty( $this->metadata ) ) {
			$data['metadata'] = $this->metadata;
		}
		if ( $this->priority ) {
			$data['metadata'] = $data['metadata'] ?? array();

			$data['metadata']['priority'] = $this->priority;
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
		return ( new self( ResolutionAction::REMOVE_ITEM ) )
			->label( 'Remove from cart' )
			->priority( $priority )
			->metadata( $metadata );
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
		return ( new self( ResolutionAction::UPDATE_ADDRESS ) )
			->label( $label )
			->priority( $priority )
			->metadata( $metadata );
	}

	/**
	 * Factory: Modify cart (e.g., reduce quantity).
	 *
	 * @param string $label    Action description.
	 * @param array  $metadata Optional. Additional metadata (e.g., max_quantity).
	 * @return self
	 */
	public static function modify_cart( string $label, array $metadata = array() ): self {
		return ( new self( ResolutionAction::MODIFY_CART ) )
			->label( $label )
			->metadata( $metadata );
	}

	/**
	 * Factory: Suggest alternative product.
	 *
	 * @param string $label    Optional. Custom label.
	 * @param array  $metadata Optional. Additional metadata.
	 * @return self
	 */
	public static function suggest_alternative( string $label = 'View similar items', array $metadata = array() ): self {
		return ( new self( ResolutionAction::SUGGEST_ALTERNATIVE ) )
			->label( $label )
			->metadata( $metadata );
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

		return ( new self( ResolutionAction::PROVIDE_MISSING_FIELD ) )
			->label( $label )
			->metadata( $metadata );
	}

	/**
	 * Factory: Wait for product restock.
	 *
	 * @param string $label    Optional. Custom label.
	 * @param array  $metadata Optional. Additional metadata.
	 * @return self
	 */
	public static function wait_for_restock( string $label = 'Wait for restock', array $metadata = array() ): self {
		return ( new self( ResolutionAction::WAIT_FOR_RESTOCK ) )
			->label( $label )
			->metadata( $metadata );
	}

	/**
	 * Factory: Use different currency.
	 *
	 * @param string $label             Action description.
	 * @param string $expected_currency The expected currency code.
	 * @param array  $metadata          Optional. Additional metadata.
	 * @return self
	 */
	public static function use_different_currency( string $label, string $expected_currency, array $metadata = array() ): self {
		$metadata['expected_currency'] = $expected_currency;

		return ( new self( ResolutionAction::USE_DIFFERENT_CURRENCY ) )
			->label( $label )
			->metadata( $metadata );
	}

	/**
	 * Factory: Accept new price.
	 *
	 * @param string $label    Action description.
	 * @param array  $metadata Optional. Additional metadata (e.g., cost_impact).
	 * @return self
	 */
	public static function accept_new_price( string $label, array $metadata = array() ): self {
		return ( new self( ResolutionAction::ACCEPT_NEW_PRICE ) )
			->label( $label )
			->metadata( $metadata );
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
		return ( new self( ResolutionAction::REDIRECT_TO_MERCHANT ) )
			->label( $label )
			->url( $url )
			->metadata( $metadata );
	}

	/**
	 * Factory: Update shipping method.
	 *
	 * @param string $label    Action description.
	 * @param array  $metadata Optional. Additional metadata.
	 * @return self
	 */
	public static function update_shipping_method( string $label, array $metadata = array() ): self {
		return ( new self( ResolutionAction::UPDATE_SHIPPING_METHOD ) )
			->label( $label )
			->metadata( $metadata );
	}

	/**
	 * Factory: Contact support.
	 *
	 * @param string $label    Optional. Custom label.
	 * @param array  $metadata Optional. Additional metadata.
	 * @return self
	 */
	public static function contact_support( string $label = 'Contact support', array $metadata = array() ): self {
		return ( new self( ResolutionAction::CONTACT_SUPPORT ) )
			->label( $label )
			->metadata( $metadata );
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
		return ( new self( ResolutionAction::REMOVE_COUPON ) )
			->label( $label )
			->priority( $priority )
			->metadata( $metadata );
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
		return ( new self( ResolutionAction::APPLY_DIFFERENT_COUPON ) )
			->label( $label )
			->priority( $priority )
			->metadata( $metadata );
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
		return ( new self( ResolutionAction::KEEP_CURRENT_COUPON ) )
			->label( $label )
			->priority( $priority )
			->metadata( $metadata );
	}
}
