<?php
/**
 * Resolution option for validation issues.
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\Validation\\Resolution
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Validation\Resolution;

/**
 * Immutable resolution option builder with factory methods.
 */
abstract class ResolutionOption {
	protected const RESOLUTION_ACTION = '';

	private ?string $label    = null;
	private ?string $url      = null;
	private ?string $priority = null;
	private array $metadata   = array();

	final private function __construct() {
	}

	/**
	 * Creates a new resolution option instance.
	 *
	 * @return static
	 */
	public static function create(): self {
		return new static();
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
	 * Sets (or unsets) a single meta value.
	 *
	 * @param string     $meta_key   The meta-key to update.
	 * @param null|mixed $meta_value The new value, or null to unset.
	 */
	public function set_meta( string $meta_key, $meta_value = null ): self {
		if ( null === $meta_value ) {
			unset( $this->metadata[ $meta_key ] );
		} else {
			$this->metadata[ $meta_key ] = $meta_value;
		}

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
			'action' => static::RESOLUTION_ACTION,
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
}
