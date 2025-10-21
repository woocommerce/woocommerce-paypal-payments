<?php

class WP_Error {
	private $code;
	private $message;
	private $data;

	public function __construct( $code = '', $message = '', $data = '' ) {
		$this->code    = $code;
		$this->message = $message;
		$this->data    = $data;
	}

	public function get_error_code() {
		return $this->code;
	}

	public function get_error_message( $code = '' ) {
		return $this->message;
	}

	public function get_error_data( $code = '' ) {
		return $this->data;
	}
}
