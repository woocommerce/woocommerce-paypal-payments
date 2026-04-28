<?php

if ( ! class_exists( 'WC_Validation' ) ) {
	class WC_Validation {
		public static function is_postcode( string $postcode, string $country ): bool {
			return true;
		}
	}
}
