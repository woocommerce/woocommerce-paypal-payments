<?php
/**
 * Interface for all integration controllers.
 *
 * @package WooCommerce\PayPalCommerce\OrderTracking\Shipment
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Compat;

interface Integration
{
    /**
     * Integrates some (possibly external) service with PayPal Payments.
     */
    public function integrate(): void;
}
