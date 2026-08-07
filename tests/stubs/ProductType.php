<?php

namespace Automattic\WooCommerce\Enums;

/**
 * Stub for WooCommerce ProductType enum class.
 */
final class ProductType {
	/**
	 * Simple product type.
	 *
	 * @var string
	 */
	public const SIMPLE = 'simple';

	/**
	 * Variable product type.
	 *
	 * @var string
	 */
	public const VARIABLE = 'variable';

	/**
	 * Grouped product type.
	 *
	 * @var string
	 */
	public const GROUPED = 'grouped';

	/**
	 * External/Affiliate product type.
	 *
	 * @var string
	 */
	public const EXTERNAL = 'external';

	/**
	 * Variation product type.
	 *
	 * @var string
	 */
	public const VARIATION = 'variation';
}
