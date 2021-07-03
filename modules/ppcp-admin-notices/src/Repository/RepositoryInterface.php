<?php
/**
 * The repository interface.
 *
 * @package WooCommerce\PayPalCommerce\AdminNotices\Repository
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\AdminNotices\Repository;

use WooCommerce\PayPalCommerce\AdminNotices\Entity\Message;

/**
 * Interface RepositoryInterface
 */
interface RepositoryInterface {


	/**
	 * Returns the current messages.
	 *
	 * @return Message[]
	 */
	public function current_message(): array;
}
