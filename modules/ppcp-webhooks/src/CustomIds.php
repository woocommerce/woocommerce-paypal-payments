<?php
/**
 * The constants for handling custom_id in the webhook requests.
 *
 * @package WooCommerce\PayPalCommerce\Webhooks
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Webhooks;

/**
 * Interface CustomIds
 */
interface CustomIds {

	public const CUSTOMER_ID_PREFIX = 'pcp_customer_';
}
