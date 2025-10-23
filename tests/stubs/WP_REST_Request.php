<?php
/**
 * Stub for WP_REST_Request
 */

class WP_REST_Request {
	protected $method;
	protected $route;
	protected $body;
	protected $params = array();

	public function __construct( $method = '', $route = '' ) {
		$this->method = $method;
		$this->route  = $route;
	}

	public function get_method() {
		return $this->method;
	}

	public function get_route() {
		return $this->route;
	}

	public function set_body( $body ) {
		$this->body = $body;
	}

	public function get_body() {
		return $this->body;
	}

	public function set_param( $key, $value ) {
		$this->params[ $key ] = $value;
	}

	public function get_param( $key ) {
		return $this->params[ $key ] ?? null;
	}

	public function get_params() {
		return $this->params;
	}
}
