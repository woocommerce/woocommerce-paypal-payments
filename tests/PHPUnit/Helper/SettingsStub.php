<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Helper;

use WooCommerce\PayPalCommerce\WcGateway\Exception\NotFoundException;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;

class SettingsStub extends Settings
{
	/**
	 * @var array
	 */
	protected $data;

	/**
	 * @param array $data
	 */
	public function __construct(array $data) {
		$this->data = $data;
	}

	public function get($id) {
		if ( ! $this->has( $id ) ) {
			throw new NotFoundException();
		}

		return $this->data[$id];
	}

	public function has($id) {
		return array_key_exists( $id, $this->data );
	}

	public function set($id, $value) {
		$this->data[$id] = $value;
	}

	public function persist() {
	}
}
