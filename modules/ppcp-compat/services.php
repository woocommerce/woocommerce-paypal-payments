<?php
/**
 * The compatibility module services.
 *
 * @package WooCommerce\PayPalCommerce\Compat
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Compat;

return array(

	'compat.ppec.settings_importer' => static function( $container ) : PPECSettingsImporter {
		$settings = $container->get( 'wcgateway.settings' );

		return new PPECSettingsImporter( $settings );
	},

);
