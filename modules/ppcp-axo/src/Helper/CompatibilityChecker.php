<?php

/**
 * Fastlane compatibility checker.
 * Detects compatibility issues and generates relevant notices.
 *
 * @package WooCommerce\PayPalCommerce\Axo\Helper
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Axo\Helper;

use WooCommerce\PayPalCommerce\WcGateway\Helper\CartCheckoutDetector;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
use WooCommerce\PayPalCommerce\WcGateway\Exception\NotFoundException;
use WooCommerce\PayPalCommerce\WcGateway\Helper\CardPaymentsConfiguration;
/**
 * Class CompatibilityChecker
 *
 * DI service: 'axo.helpers.compatibility-checker'
 */
class CompatibilityChecker
{
    /**
     * The list of Fastlane incompatible plugin names.
     *
     * @var string[]
     */
    protected array $incompatible_plugin_names;
    /**
     * Stores the result of checkout compatibility checks.
     *
     * @var array
     */
    protected array $checkout_compatibility;
    /**
     * Provides details about the DCC configuration.
     *
     * @var CardPaymentsConfiguration
     */
    private CardPaymentsConfiguration $dcc_configuration;
    /**
     * CompatibilityChecker constructor.
     *
     * @param string[]                  $incompatible_plugin_names The list of Fastlane incompatible
     *                                                             plugin names.
     * @param CardPaymentsConfiguration $dcc_configuration         DCC gateway configuration.
     */
    public function __construct(array $incompatible_plugin_names, CardPaymentsConfiguration $dcc_configuration)
    {
        $this->incompatible_plugin_names = $incompatible_plugin_names;
        $this->dcc_configuration = $dcc_configuration;
        $this->checkout_compatibility = array('has_elementor_checkout' => null, 'has_classic_checkout' => null, 'has_block_checkout' => null);
    }
    /**
     * Checks if the checkout uses Elementor.
     *
     * @return bool Whether the checkout uses Elementor.
     */
    protected function has_elementor_checkout(): bool
    {
        if ($this->checkout_compatibility['has_elementor_checkout'] === null) {
            $this->checkout_compatibility['has_elementor_checkout'] = CartCheckoutDetector::has_elementor_checkout();
        }
        return $this->checkout_compatibility['has_elementor_checkout'];
    }
    /**
     * Checks if the checkout uses classic checkout.
     *
     * @return bool Whether the checkout uses classic checkout.
     */
    protected function has_classic_checkout(): bool
    {
        if ($this->checkout_compatibility['has_classic_checkout'] === null) {
            $this->checkout_compatibility['has_classic_checkout'] = CartCheckoutDetector::has_classic_checkout();
        }
        return $this->checkout_compatibility['has_classic_checkout'];
    }
    /**
     * Checks if the checkout uses block checkout.
     *
     * @return bool Whether the checkout uses block checkout.
     */
    protected function has_block_checkout(): bool
    {
        if ($this->checkout_compatibility['has_block_checkout'] === null) {
            $this->checkout_compatibility['has_block_checkout'] = CartCheckoutDetector::has_block_checkout();
        }
        return $this->checkout_compatibility['has_block_checkout'];
    }
    /**
     * Generates the full HTML of the notification.
     *
     * @param string $message     HTML of the inner message contents.
     * @param bool   $is_error    Whether the provided message is an error. Affects the notice
     *                            color.
     * @param bool   $raw_message Whether to return raw message without HTML wrappers.
     *
     * @return string The full HTML code of the notification, or an empty string, or raw message.
     */
    private function render_notice(string $message, bool $is_error = \false, bool $raw_message = \false): string
    {
        if (!$message) {
            return '';
        }
        if ($raw_message) {
            return $message;
        }
        return sprintf('<div class="ppcp-notice %1$s"><p>%2$s</p></div>', $is_error ? 'ppcp-notice-error' : '', $message);
    }
    /**
     * Check if there aren't any incompatibilities that would prevent Fastlane from working
     * properly.
     *
     * @return bool Whether the setup is compatible.
     */
    public function is_fastlane_compatible(): bool
    {
        // Check for incompatible plugins.
        if (!empty($this->incompatible_plugin_names)) {
            return \false;
        }
        // Check for checkout page incompatibilities.
        if ($this->has_elementor_checkout()) {
            return \false;
        }
        if (!$this->has_classic_checkout() && !$this->has_block_checkout()) {
            return \false;
        }
        // No incompatibilities found.
        return \true;
    }
    /**
     * Generates the checkout notice.
     *
     * @param bool $raw_message Whether to return raw message without HTML wrappers.
     * @return string
     */
    public function generate_checkout_notice(bool $raw_message = \false): string
    {
        $notice_content = '';
        // Check for checkout incompatibilities.
        $has_checkout_incompatibility = $this->has_elementor_checkout() || !$this->has_classic_checkout() && !$this->has_block_checkout();
        if (!$has_checkout_incompatibility) {
            return '';
        }
        $checkout_page_link = esc_url(get_edit_post_link(wc_get_page_id('checkout')) ?? '');
        $block_checkout_docs_link = __('https://woocommerce.com/document/woocommerce-store-editing/customizing-cart-and-checkout/#using-the-cart-and-checkout-blocks', 'woocommerce-paypal-payments');
        if ($this->has_elementor_checkout()) {
            $notice_content = sprintf(
                /* translators: %1$s: URL to the Checkout edit page. %2$s: URL to the block checkout docs. */
                __('<span class="highlight">Warning:</span> The <a href="%1$s">Checkout page</a> of your store currently uses the <code>Elementor Checkout widget</code>. To enable Fastlane and accelerate payments, the page must include either the <code>Checkout</code> block, <code>Classic Checkout</code>, or the <code>[woocommerce_checkout]</code> shortcode. See <a href="%2$s">this page</a> for instructions on how to switch to the Checkout block.', 'woocommerce-paypal-payments'),
                esc_url($checkout_page_link),
                esc_url($block_checkout_docs_link)
            );
        } elseif (!$this->has_classic_checkout() && !$this->has_block_checkout()) {
            $notice_content = sprintf(
                /* translators: %1$s: URL to the Checkout edit page. %2$s: URL to the block checkout docs. */
                __('<span class="highlight">Warning:</span> The <a href="%1$s">Checkout page</a> of your store does not seem to be properly configured or uses an incompatible <code>third-party Checkout</code> solution. To enable Fastlane and accelerate payments, the page must include either the <code>Checkout</code> block, <code>Classic Checkout</code>, or the <code>[woocommerce_checkout]</code> shortcode. See <a href="%2$s">this page</a> for instructions on how to switch to the Checkout block.', 'woocommerce-paypal-payments'),
                esc_url($checkout_page_link),
                esc_url($block_checkout_docs_link)
            );
        }
        return $this->render_notice($notice_content, \true, $raw_message);
    }
    /**
     * Generates the incompatible plugins notice.
     *
     * @param bool $raw_message Whether to return raw message without HTML wrappers.
     * @return string
     */
    public function generate_incompatible_plugins_notice(bool $raw_message = \false): string
    {
        if (empty($this->incompatible_plugin_names)) {
            return '';
        }
        $plugins_settings_link = esc_url(admin_url('plugins.php'));
        $notice_content = sprintf(
            /* translators: %1$s: URL to the plugins settings page. %2$s: List of incompatible plugins. */
            __('<span class="highlight">Note:</span> The accelerated guest buyer experience provided by Fastlane may not be fully compatible with some of the following <a href="%1$s">active plugins</a>: <ul class="ppcp-notice-list">%2$s</ul>', 'woocommerce-paypal-payments'),
            $plugins_settings_link,
            implode('', $this->incompatible_plugin_names)
        );
        return $this->render_notice($notice_content, \false, $raw_message);
    }
    /**
     * Generates a warning notice with instructions on conflicting plugin-internal settings.
     *
     * @param bool $raw_message Whether to return raw message without HTML wrappers.
     * @return string
     */
    public function generate_settings_conflict_notice(bool $raw_message = \false): string
    {
        if ($this->dcc_configuration->is_enabled()) {
            return '';
        }
        $notice_content = __('<span class="highlight">Warning:</span> To enable Fastlane and accelerate payments, the <strong>Advanced Card Processing</strong> payment method must also be enabled.', 'woocommerce-paypal-payments');
        return $this->render_notice($notice_content, \true, $raw_message);
    }
}
