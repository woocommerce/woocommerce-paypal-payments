<?php
declare(strict_types=1);

if ( ! class_exists( 'WC_Payment_Token' ) ) {
	/**
	 * Minimal functional stub of WooCommerce's WC_Payment_Token.
	 *
	 * Backs get_meta()/add_meta_data() with a plain array so that the real
	 * PaymentTokenPayPal/PaymentTokenVenmo/PaymentTokenApplePay subclasses under test
	 * (which call these methods) behave as they do in a real WooCommerce install.
	 */
	abstract class WC_Payment_Token {
		/**
		 * @var int
		 */
		private $id = 0;

		/**
		 * @var string
		 */
		private $gateway_id = '';

		/**
		 * @var int
		 */
		private $user_id = 0;

		/**
		 * @var array<string, mixed>
		 */
		private $meta = array();

		public function get_id() {
			return $this->id;
		}

		public function set_id( $id ) {
			$this->id = (int) $id;
		}

		public function get_gateway_id( $context = 'view' ) {
			return $this->gateway_id;
		}

		public function set_gateway_id( $gateway_id ) {
			$this->gateway_id = (string) $gateway_id;
		}

		public function get_user_id( $context = 'view' ) {
			return $this->user_id;
		}

		public function set_user_id( $user_id ) {
			$this->user_id = (int) $user_id;
		}

		public function get_token( $context = 'view' ) {
			return '';
		}

		public function get_meta( $key = '', $single = true, $context = 'view' ) {
			return $this->meta[ $key ] ?? '';
		}

		public function add_meta_data( $key, $value, $unique = false ) {
			$this->meta[ $key ] = $value;
		}
	}
}
