<?php
/**
 * The services of the admin notice module.
 *
 * @package WooCommerce\PayPalCommerce\Button
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\AdminNotices;

use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\AdminNotices\Renderer\Renderer;
use WooCommerce\PayPalCommerce\AdminNotices\Renderer\RendererInterface;
use WooCommerce\PayPalCommerce\AdminNotices\Repository\Repository;
use WooCommerce\PayPalCommerce\AdminNotices\Repository\RepositoryInterface;
use WooCommerce\PayPalCommerce\AdminNotices\Endpoint\MuteMessageEndpoint;

return array(
	'admin-notices.get_module_asset_url'  => static function ( ContainerInterface $container ): callable {
		/** @var $getter callable(string, string):string */
		$getter = $container->get( 'assets.get_module_asset_url' );

		return static function ( string $asset_name ) use ( $getter ): string {
			return ( $getter )( 'ppcp-admin-notices', $asset_name );
		};
	},
	'admin-notices.renderer'              => static function ( ContainerInterface $container ): RendererInterface {
		return new Renderer(
			$container->get( 'admin-notices.repository' ),
			$container->get( 'admin-notices.get_module_asset_url' ),
			$container->get( 'ppcp.asset-version' )
		);
	},
	'admin-notices.repository'            => static function ( ContainerInterface $container ): RepositoryInterface {
		return new Repository();
	},
	'admin-notices.mute-message-endpoint' => static function ( ContainerInterface $container ): MuteMessageEndpoint {
		return new MuteMessageEndpoint(
			$container->get( 'button.request-data' ),
			$container->get( 'admin-notices.repository' )
		);
	},
);
