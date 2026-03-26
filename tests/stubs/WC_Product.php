<?php
/**
 * Minimal WC_Product class hierarchy stubs for unit tests.
 *
 * These stubs ensure that Mockery mocks respect the WooCommerce class
 * inheritance chain (e.g. WC_Product_Simple extends WC_Product).
 */

if ( ! class_exists( 'WC_Product' ) ) {
	class WC_Product {
	}
}

if ( ! class_exists( 'WC_Product_Simple' ) ) {
	class WC_Product_Simple extends WC_Product {
	}
}

if ( ! class_exists( 'WC_Product_Variable' ) ) {
	class WC_Product_Variable extends WC_Product {
	}
}

if ( ! class_exists( 'WC_Product_Variation' ) ) {
	class WC_Product_Variation extends WC_Product_Simple {
	}
}
