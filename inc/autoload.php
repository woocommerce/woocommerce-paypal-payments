<?php
/**
 * The custom autoloader functionality.
 *
 * @package Inpsyde\PayPalCommerce
 */

declare( strict_types=1 );

namespace Inpsyde\PayPalCommerce;

/**
 * The autoloader.
 */
function autoload() {

	/**
	 * Custom WordPress autoloader.
	 */
	spl_autoload_register(
		function( $class_name ) {
			if (
				strpos( $class_name, 'Inpsyde\PayPalCommerce' ) === false
				&& strpos( $class_name, 'Inpsyde\Woocommerce' ) === false
			) {
				return;
			}
			$class_parts = explode( '\\', $class_name );
			$module_dir  = dirname( __DIR__ ) . '/modules.local/';
			$modules     = array(
				'ApiClient'    => $module_dir . 'ppcp-api-client/src/',
				'Button'       => $module_dir . 'ppcp-button/src/',
				'Logging'      => $module_dir . 'woocommerce-logging/src/',
				'Onboarding'   => $module_dir . 'ppcp-onboarding/src/',
				'Session'      => $module_dir . 'ppcp-session/src/',
				'Subscription' => $module_dir . 'ppcp-subscription/src/',
				'WcGateway'    => $module_dir . 'ppcp-wc-gateway/src/',
				'Webhooks'     => $module_dir . 'ppcp-webhooks/src/',
			);

			if ( isset( $class_parts[2] ) && ! isset( $class_parts[3] ) ) {
				$file_path = dirname( __DIR__ ) . '/src/class-' . strtolower( $class_parts[2] ) . '.php';
				include $file_path;
				return;
			}

			if ( ! isset( $modules[ $class_parts[2] ] ) ) {
				return;
			}

			$file_path = $modules[ $class_parts[2] ];
			unset( $class_parts[0] );
			unset( $class_parts[1] );
			unset( $class_parts[2] );
			$file_name = 'class-' . strtolower( end( $class_parts ) ) . '.php';
			array_pop( $class_parts );

			$file_path .= implode( DIRECTORY_SEPARATOR, $class_parts ) . '/' . $file_name;
			include $file_path;
		}
	);

	// Load composer autoloader.
	include dirname( __DIR__ ) . '/vendor/autoload.php';

}
