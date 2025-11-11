<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Setting;

use WooCommerce\PayPalCommerce\Settings\Extension\ExtensionDataModel;

class AgenticSettingsDataModel extends ExtensionDataModel {

	protected const NAME = 'agentic';

	protected function get_defaults(): array {
		return array(
			'active' => true,
		);
	}

	public function is_active(): bool {
		return (bool) $this->data['active'];
	}

	public function set_active( bool $state ): void {
		$this->data['active'] = $state;
	}
}
