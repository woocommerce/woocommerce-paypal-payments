<?php

if ( ! class_exists( 'WC_Validation' ) ) {
	class WC_Validation {
		public static function is_postcode( string $postcode, string $country ): bool {
			if ( strlen( trim( preg_replace( '/[\s\-A-Za-z0-9]/', '', $postcode ) ) ) > 0 ) {
				return false;
			}
			return true;
		}
	}
}
