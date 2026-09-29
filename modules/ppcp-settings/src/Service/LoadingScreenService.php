<?php

/**
 * Provides loading screen handling logic for PayPal settings page.
 *
 * @package WooCommerce\PayPalCommerce\Settings\Service
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Settings\Service;

/**
 * LoadingScreenService class. Handles the display of loading screen for the PayPal settings page.
 */
class LoadingScreenService
{
    /**
     * Register hooks.
     *
     * @return void
     */
    public function register(): void
    {
        if (!is_admin()) {
            return;
        }
        add_action('admin_head', array($this, 'add_settings_loading_screen'));
    }
    /**
     * Add CSS to permanently hide specific WooCommerce elements on the PayPal settings page.
     *
     * @return void
     */
    public function add_settings_loading_screen(): void
    {
        // Only run on the specific WooCommerce PayPal settings page.
        if (!$this->is_ppcp_settings_page()) {
            return;
        }
        ?>
		<style>
			/* Permanently hide these WooCommerce elements. */
			.woocommerce form#mainform > *:not(#ppcp-settings-container),
			#woocommerce-embedded-root {
				display: none;
			}

			#wpcontent #wpbody {
				margin-top: 0;
			}
		</style>
		<?php 
    }
    /**
     * Check if we're on the PayPal checkout settings page.
     *
     * @return bool True if we're on the PayPal settings page
     */
    private function is_ppcp_settings_page(): bool
    {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        $page = wc_clean(wp_unslash($_GET['page'] ?? ''));
        $tab = wc_clean(wp_unslash($_GET['tab'] ?? ''));
        $section = wc_clean(wp_unslash($_GET['section'] ?? ''));
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        return $page === 'wc-settings' && $tab === 'checkout' && $section === 'ppcp-gateway';
    }
}
