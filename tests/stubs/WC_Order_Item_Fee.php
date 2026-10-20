<?php

if ( ! class_exists( 'WC_Order_Item_Fee' ) ) {
	/**
	 * Minimal stand-in for the WooCommerce fee line item.
	 *
	 * The real WooCommerce classes are not loaded in this unit test environment, and
	 * WooCommerceOrderCreator::configure_fees() instantiates WC_Order_Item_Fee directly
	 * (it is not injected, so it cannot be swapped for a Mockery mock). This stub only
	 * implements the setters/getters configure_fees() actually uses.
	 */
	class WC_Order_Item_Fee {
		private string $name = '';
		private string $amount = '0';
		private string $total = '0';
		private string $tax_status = '';
		private string $tax_class = '';

		public function set_name( string $name ): void {
			$this->name = $name;
		}

		public function get_name(): string {
			return $this->name;
		}

		public function set_amount( string $amount ): void {
			$this->amount = $amount;
		}

		public function get_amount(): string {
			return $this->amount;
		}

		public function set_total( string $total ): void {
			$this->total = $total;
		}

		public function get_total(): string {
			return $this->total;
		}

		public function set_tax_status( string $tax_status ): void {
			$this->tax_status = $tax_status;
		}

		public function get_tax_status(): string {
			return $this->tax_status;
		}

		public function set_tax_class( string $tax_class ): void {
			$this->tax_class = $tax_class;
		}

		public function get_tax_class(): string {
			return $this->tax_class;
		}
	}
}
