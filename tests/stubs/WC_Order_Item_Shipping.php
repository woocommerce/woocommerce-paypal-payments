<?php

if ( ! class_exists( 'WC_Order_Item_Shipping' ) ) {
	/**
	 * Minimal stand-in for the WooCommerce shipping line item.
	 *
	 * The real WooCommerce classes are not loaded in this unit test environment, and
	 * WooCommerceOrderCreator::configure_addresses() instantiates WC_Order_Item_Shipping
	 * directly (it is not injected, so it cannot be swapped for a Mockery mock). This stub only
	 * implements the setters/getters configure_addresses() actually uses.
	 */
	class WC_Order_Item_Shipping {
		private string $method_title = '';
		private string $method_id    = '';
		private string $instance_id  = '';
		private string $total        = '0';

		public function set_method_title( string $method_title ): void {
			$this->method_title = $method_title;
		}

		public function get_method_title(): string {
			return $this->method_title;
		}

		public function set_method_id( string $method_id ): void {
			$this->method_id = $method_id;
		}

		public function get_method_id(): string {
			return $this->method_id;
		}

		public function set_instance_id( string $instance_id ): void {
			$this->instance_id = $instance_id;
		}

		public function get_instance_id(): string {
			return $this->instance_id;
		}

		public function set_total( string $total ): void {
			$this->total = $total;
		}

		public function get_total(): string {
			return $this->total;
		}

		public function add_meta_data( string $key, $value, bool $unique = false ): void {}
	}
}
