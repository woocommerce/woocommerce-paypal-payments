<?php

if (!class_exists('WC_Session')) {
	/**
	 * Stub for WooCommerce Session base class
	 */
	abstract class WC_Session {
		/**
		 * Session data.
		 *
		 * @var array
		 */
		protected $_data = [];

		/**
		 * Dirty when the session needs saving.
		 *
		 * @var bool
		 */
		protected $_dirty = false;

		/**
		 * Get a session variable.
		 *
		 * @param string $key Key.
		 * @param mixed  $default Default value.
		 * @return mixed
		 */
		public function get($key, $default = null) {
			return isset($this->_data[$key]) ? $this->_data[$key] : $default;
		}

		/**
		 * Set a session variable.
		 *
		 * @param string $key Key.
		 * @param mixed  $value Value.
		 */
		public function set($key, $value) {
			if ($value !== $this->get($key)) {
				$this->_data[$key] = $value;
				$this->_dirty = true;
			}
		}

		/**
		 * Get customer ID.
		 *
		 * @return int
		 */
		public function get_customer_id() {
			return 0;
		}
	}
}

if (!class_exists('WC_Session_Handler')) {
	/**
	 * Stub for WooCommerce Session Handler
	 */
	class WC_Session_Handler extends WC_Session {
		/**
		 * Constructor
		 */
		public function __construct() {
			$this->_data = [];
		}

		/**
		 * Init hooks and session data.
		 */
		public function init() {}

		/**
		 * Get session cookie.
		 *
		 * @return bool|array
		 */
		public function get_session_cookie() {
			return false;
		}

		/**
		 * Get session data.
		 *
		 * @return array
		 */
		public function get_session_data() {
			return $this->_data;
		}

		/**
		 * Save data.
		 */
		public function save_data() {}

		/**
		 * Destroy all session data.
		 */
		public function destroy_session() {
			$this->_data = [];
		}

		/**
		 * Forget session.
		 */
		public function forget_session() {
			$this->destroy_session();
		}

		/**
		 * Cleanup sessions.
		 */
		public function cleanup_sessions() {}
	}
}
