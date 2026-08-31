<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\SdkV6\Helper;

/**
 * Exposes SessionRecord's protected storage API, so the shared behaviour can
 * be exercised without standing in for any one of the real records.
 */
class TestRecord extends SessionRecord {

	public const KEY = 'ppcp_test_record';

	protected const SESSION_KEY = self::KEY;

	/**
	 * @param mixed $value The value to remember.
	 */
	public function write( $value ): void {
		$this->remember( $value );
	}

	/**
	 * @return mixed
	 */
	public function read() {
		return $this->remembered();
	}
}
