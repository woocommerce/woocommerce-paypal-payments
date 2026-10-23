<?php

/**
 * The custom_id that binds a PayPal Subscription to the current shopper session.
 *
 * @package WooCommerce\PayPalCommerce\Button\Helper
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Button\Helper;

use WC_Session_Handler;
use WooCommerce\PayPalCommerce\Webhooks\CustomIds;
/**
 * Mirrors the value stamped on cart orders by PurchaseUnitFactory::from_wc_cart();
 * both must stay in sync so that ApproveSubscriptionEndpoint can validate
 * ownership on approval.
 */
class SubscriptionCustomId
{
    /**
     * The custom_id for the current session, or an empty string without one.
     */
    public static function for_current_session(): string
    {
        $session = WC()->session;
        if ($session instanceof WC_Session_Handler) {
            $session_id = $session->get_customer_unique_id();
            if ($session_id) {
                return CustomIds::CUSTOMER_ID_PREFIX . $session_id;
            }
        }
        return '';
    }
}
