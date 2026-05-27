<?php

namespace Automattic\WooCommerce\Enums;

/**
 * Stub for WooCommerce ProductStatus enum class.
 */
final class ProductStatus {
	/**
	 * The product is in auto-draft status.
	 *
	 * @var string
	 */
	public const AUTO_DRAFT = 'auto-draft';

	/**
	 * The product is in draft status.
	 *
	 * @var string
	 */
	public const DRAFT = 'draft';

	/**
	 * The product is in pending status.
	 *
	 * @var string
	 */
	public const PENDING = 'pending';

	/**
	 * The product is in private status.
	 *
	 * @var string
	 */
	public const PRIVATE = 'private';

	/**
	 * The product is in publish status.
	 *
	 * @var string
	 */
	public const PUBLISH = 'publish';

	/**
	 * The product is in trash status.
	 *
	 * @var string
	 */
	public const TRASH = 'trash';

	/**
	 * The product is in future status.
	 *
	 * @var string
	 */
	public const FUTURE = 'future';
}