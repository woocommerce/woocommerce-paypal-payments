<?php
/**
 * The products of a seller status.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Entity
 */

declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\ApiClient\Entity;

/**
 * Class SellerStatusProduct
 */
class SellerStatusProduct {

	const VETTING_STATUS_APPROVED   = 'APPROVED';
	const VETTING_STATUS_PENDING    = 'PENDING';
	const VETTING_STATUS_DECLINED   = 'DECLINED';
	const VETTING_STATUS_SUBSCRIBED = 'SUBSCRIBED';
	const VETTING_STATUS_IN_REVIEW  = 'IN_REVIEW';
	const VETTING_STATUS_DENIED     = 'DENIED';
	/**
	 * The name of the product.
	 *
	 * @var string
	 */
	private $name;

	/**
	 * The vetting status of the product.
	 *
	 * @var string
	 */
	private $vetting_status;

	/**
	 * The capabilities of the product.
	 *
	 * @var string[]
	 */
	private $capabilities;

	/**
	 * SellerStatusProduct constructor.
	 *
	 * @param string   $name           The name of the product.
	 * @param string   $vetting_status The vetting status of the product.
	 * @param string[] $capabilities The capabilities of the product.
	 */
	public function __construct(
		string $name,
		string $vetting_status,
		array $capabilities
	) {
		foreach ( $capabilities as $key => $capability ) {
			if ( is_string( $capability ) ) {
				continue;
			}
			unset( $capabilities[ $key ] );
		}
		$this->name           = $name;
		$this->vetting_status = $vetting_status;
		$this->capabilities   = $capabilities;
	}

	/**
	 * Returns the name of the product.
	 *
	 * @return string
	 */
	public function name() : string {
		return $this->name;
	}

	/**
	 * Returns the vetting status for this product.
	 *
	 * @return string
	 */
	public function vetting_status() : string {
		return $this->vetting_status;
	}

	/**
	 * Returns the capabilities of this product.
	 *
	 * @return string[]
	 */
	public function capabilities() : array {
		return $this->capabilities;
	}

	/**
	 * Returns the entity as array.
	 *
	 * @return array
	 */
	public function to_array() : array {
		return array(
			'name'           => $this->name(),
			'vetting_status' => $this->vetting_status(),
			'capabilities'   => $this->capabilities(),
		);
	}


}
