<?php
/**
 * Adds generate_ppcp_html method for rendering settings.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Gateway
 */

declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\WcGateway\Gateway;

use WooCommerce\PayPalCommerce\WcGateway\Settings\SettingsRenderer;

/**
 * Trait GatewaySettingsRendererTrait
 */
trait GatewaySettingsRendererTrait {
	/**
	 * Renders the settings.
	 *
	 * @return string
	 */
	public function generate_ppcp_html(): string {
		ob_start();
		$this->settings_renderer()->render();
		$content = ob_get_contents();
		ob_end_clean();
		return $content;
	}

	/**
	 * Returns the settings renderer.
	 *
	 * @return SettingsRenderer
	 */
	abstract protected function settings_renderer(): SettingsRenderer;
}
