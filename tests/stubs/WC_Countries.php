<?php

if ( ! class_exists( 'WC_Countries' ) ) {
	class WC_Countries {
		public function get_shipping_countries() {
			return array(
				'US' => 'United States (US)',
				'DE' => 'Germany',
			);
		}
	}
}
