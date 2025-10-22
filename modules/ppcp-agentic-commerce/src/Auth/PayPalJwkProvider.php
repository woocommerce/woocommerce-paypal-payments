<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Auth;

class PayPalJwkProvider {

	public function keys(): array {
		return $this->get_cached() ?? $this->fetch_keys();
	}

	protected function get_cached(): ?array {
		return array(
			'key1' => 'value1',
			'key2' => 'value2',
		);
	}

	protected function fetch_keys(): array {
		return array();
	}
}
