<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Setting;

use WooCommerce\PayPalCommerce\Settings\Endpoint\ExtensionRestEndpoint;

/**
 * REST controller for the settings extension.
 */
class AgenticSettingsEndpoint extends ExtensionRestEndpoint {
	protected $rest_base = 'agentic-settings';

	protected function sanitize_rest_data( array $data ): ?array {
		return $data;
	}
}
