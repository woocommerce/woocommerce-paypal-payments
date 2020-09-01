<?php
/**
 * The repository interface.
 *
 * @package Inpsyde\PayPalCommerce\AdminNotices\Repository
 */

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\AdminNotices\Repository;

use Inpsyde\PayPalCommerce\AdminNotices\Entity\Message;

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
