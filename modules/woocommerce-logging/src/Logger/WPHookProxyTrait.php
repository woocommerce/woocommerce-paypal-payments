<?php
/**
 * The WPHookProxyTrait is used as a proxy for WordPress defined callbacks.
 *
 * @package WooCommerce\WooCommerce\Logging\Logger
 */

declare(strict_types=1);

namespace WooCommerce\WooCommerce\Logging\Logger;

use Exception;
use TypeError;

/**
 * Trait for WordPress defined callbacks
 */
trait WPHookProxyTrait {

	/**
	 * Proxy for WordPress defined callbacks
	 *
	 * This function is used when we have to call one of our methods but the callback is hooked into
	 * a WordPress filter or action.
	 *
	 * Since isn't possible to ensure third party plugins will pass the correct data declared
	 * by WordPress we need a way to prevent fatal errors without introduce complexity.
	 *
	 * In this case, this function will allow us to maintain our type hints and in case something wrong
	 * happen we rise a E_USER_NOTICE error so the issue get logged and also firing an action we allow
	 * use or third party developer to be able to perform a more accurate debug.
	 *
	 * @param callable $callback The callback.
	 * @return callable The callback.
	 * @throws Exception|TypeError In case WP_DEBUG is active.
	 *
     * phpcs:disable Inpsyde.CodeQuality.ArgumentTypeDeclaration.NoArgumentType
     * phpcs:disable Inpsyde.CodeQuality.ReturnTypeDeclaration.NoReturnType
     * phpcs:disable Generic.Metrics.NestingLevel.TooHigh
	 */
	private function wp_hook_proxy( callable $callback ): callable {
		/**
		 * Remove checks.
		 *
		 * @psalm-suppress MissingClosureParamType
         * phpcs:enable
         * phpcs:disable Inpsyde.CodeQuality.ReturnTypeDeclaration.NoReturnType
         * phpcs:disable Inpsyde.CodeQuality.ArgumentTypeDeclaration.NoArgumentType
		 */
		return static function ( ...$args ) use ( $callback ) {

            // phpcs:enable

			$returned_value = $args[0] ?? null;

			try {
				$returned_value = $callback( ...$args );
			} catch ( TypeError $thr ) {
				do_action( 'ppcp_wp_hook_proxy_log', 'error', $thr->getMessage(), compact( 'thr' ) );

				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					throw $thr;
				}
			}

			return $returned_value;
		};
	}
}
