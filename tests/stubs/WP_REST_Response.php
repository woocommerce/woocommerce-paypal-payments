<?php
declare( strict_types = 1 );

class WP_REST_Response {
	private $data;
	private $status;
	public $headers;

	function __construct( $data = null, $status = 200, $headers = array() ) {
		$this->set_data( $data );
		$this->set_status( $status );
		$this->set_headers( $headers );
	}

	public function get_headers() {
		return $this->headers;
	}

	public function set_headers( $headers ) {
		$this->headers = $headers;
	}

	public function get_status() {
		return $this->status;
	}

	public function set_status( $code ) {
		$this->status = absint( $code );
	}

	public function get_data() {
		return $this->data;
	}

	public function set_data( $data ) {
		$this->data = $data;
	}
}
