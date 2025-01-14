<?php

require_once dirname( __DIR__ ) . '/stubs/autoload.php';

if ( ! function_exists( 'wp_is_stream' ) ) {
	function wp_is_stream( $path ) {
		$scheme_separator = strpos( $path, '://' );

		if ( false === $scheme_separator ) {
			// $path isn't a stream.
			return false;
		}

		$stream = substr( $path, 0, $scheme_separator );

		return in_array( $stream, stream_get_wrappers(), true );
	}
}

if ( ! function_exists( 'wp_normalize_path' ) ) {
	function wp_normalize_path( $path ) {
		$wrapper = '';

		if ( wp_is_stream( $path ) ) {
			list( $wrapper, $path ) = explode( '://', $path, 2 );

			$wrapper .= '://';
		}

		// Standardize all paths to use '/'.
		$path = str_replace( '\\', '/', $path );

		// Replace multiple slashes down to a singular, allowing for network shares having two slashes.
		$path = preg_replace( '|(?<=.)/+|', '/', $path );

		// Windows paths should uppercase the drive letter.
		if ( ':' === substr( $path, 1, 1 ) ) {
			$path = ucfirst( $path );
		}

		return $wrapper . $path;
	}
}

if ( ! function_exists( 'rest_ensure_response' ) ) {
	function rest_ensure_response( $response ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( $response instanceof WP_REST_Response ) {
			return $response;
		}

		return new WP_REST_Response( $response );
	}
}
