<?php
declare(strict_types=1);

// Brain Monkey stubs cannot fill a by-reference argument, so this one is a real function.
if ( ! function_exists( 'wp_parse_str' ) ) {
	function wp_parse_str( $input_string, &$result ) {
		parse_str( (string) $input_string, $result );
	}
}
