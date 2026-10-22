<?php
/**
 * Stub for the WooCommerce Store API RouteException
 */

namespace Automattic\WooCommerce\StoreApi\Exceptions;

class RouteException extends \Exception {
	public $error_code;

	public $additional_data = array();

	public function __construct( $error_code, $message, $http_status_code = 400, $additional_data = array() ) {
		$this->error_code      = $error_code;
		$this->additional_data = (array) $additional_data;

		parent::__construct( $message, $http_status_code );
	}

	public function getErrorCode() {
		return $this->error_code;
	}

	public function getAdditionalData() {
		return $this->additional_data;
	}
}
