<?php

/**
 * The SDK v6 module extensions.
 *
 * @package WooCommerce\PayPalCommerce\SdkV6
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\SdkV6;

use WooCommerce\PayPalCommerce\Button\Assets\DisabledSmartButton;
use WooCommerce\PayPalCommerce\Button\Assets\SmartButtonInterface;
use WooCommerce\PayPalCommerce\SdkV6\Assets\SdkV6Manager;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
return array(
    /**
     * Both SDKs claim the window.paypal global, so the v5 smart-button
     * script must not load on pages where the v6 SDK loads. On pages v6
     * owns (classic product/cart/checkout, block cart/checkout) every
     * v5 surface consuming this service's script_data() goes dark,
     * including block card fields and the regular block method: that is
     * accepted migration-state breakage until their own stories migrate
     * them. On the pages v6 does not own (pay-now, add-payment-method)
     * the v5 stack keeps running: the blocks, applepay, googlepay and
     * axo modules would break under a blanket disable.
     *
     * MIGRATION-PHASE ONLY: this per-page handoff exists so the site and
     * the test suites stay fully functional while the v6 migration
     * stories land one surface at a time. Once every surface is v6-owned
     * and the epic is release-ready, replace this with an unconditional
     * swap (or stop loading the v5 modules entirely) — the final state is
     * a whole-flow flag flip, not per-page coexistence. Do not extend
     * this mechanism with further per-surface toggles or compatibility
     * shims.
     */
    'button.smart-button' => static function (SmartButtonInterface $smart_button, ContainerInterface $container): SmartButtonInterface {
        $manager = $container->get('sdk-v6.manager');
        assert($manager instanceof SdkV6Manager);
        if ($manager->should_load_on_current_page()) {
            return new DisabledSmartButton();
        }
        return $smart_button;
    },
);
