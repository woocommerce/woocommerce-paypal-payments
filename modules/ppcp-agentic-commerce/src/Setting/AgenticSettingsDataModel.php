<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Setting;

use WooCommerce\PayPalCommerce\Settings\Data\AbstractDataModel;

class AgenticSettingsDataModel extends AbstractDataModel {
	protected const OPTION_KEY = 'woocommerce-ppcp-ext-agentic';

	protected function get_defaults(): array {
		return array(
			'active' => true,
		);
	}

	public function get_active(): bool {
		return (bool) $this->data['active'];
	}

	public function set_active( bool $state ): void {
		$this->data['active'] = $state;
	}
}
