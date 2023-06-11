<?php
/**
 * WP-CLI commands for managing plugin settings.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Cli
 */

declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\WcGateway\Cli;

use WP_CLI;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;

/**
 * Class SettingsCommand.
 */
class SettingsCommand {
	/**
	 * The settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * SettingsCommand constructor.
	 *
	 * @param Settings $settings The settings.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Updates the specified settings.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : The setting key.
	 *
	 * <value>
	 * : The setting value.
	 *
	 * ## EXAMPLES
	 *
	 *     wp pcp settings update description "Pay via PayPal."
	 *     wp pcp settings update vault_enabled true
	 *     wp pcp settings update vault_enabled false
	 *
	 * @param array $args Positional args.
	 * @param array $assoc_args Option args.
	 */
	public function update( array $args, array $assoc_args ): void {
		$key   = (string) $args[0];
		$value = $args[1];

		if ( 'true' === strtolower( $value ) ) {
			$value = true;
		} elseif ( 'false' === strtolower( $value ) ) {
			$value = false;
		}

		$this->settings->set( $key, $value );
		$this->settings->persist();

		WP_CLI::success( "Updated '{$key}' to '{$value}'." );
	}
}
