<?php
/**
 * The services of the admin notice module.
 *
 * @package WooCommerce\PayPalCommerce\Button
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Button;

use Dhii\Data\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\AdminNotices\Renderer\Renderer;
use WooCommerce\PayPalCommerce\AdminNotices\Renderer\RendererInterface;
use WooCommerce\PayPalCommerce\AdminNotices\Repository\Repository;
use WooCommerce\PayPalCommerce\AdminNotices\Repository\RepositoryInterface;
use WooCommerce\PayPalCommerce\Button\Assets\DisabledSmartButton;
use WooCommerce\PayPalCommerce\Button\Assets\SmartButton;
use WooCommerce\PayPalCommerce\Button\Assets\SmartButtonInterface;
use WooCommerce\PayPalCommerce\Button\Endpoint\ApproveOrderEndpoint;
use WooCommerce\PayPalCommerce\Button\Endpoint\ChangeCartEndpoint;
use WooCommerce\PayPalCommerce\Button\Endpoint\CreateOrderEndpoint;
use WooCommerce\PayPalCommerce\Button\Endpoint\RequestData;
use WooCommerce\PayPalCommerce\Button\Exception\RuntimeException;

return array(
	'admin-notices.renderer'   => static function ( $container ): RendererInterface {

		$repository = $container->get( 'admin-notices.repository' );
		return new Renderer( $repository );
	},
	'admin-notices.repository' => static function ( $container ): RepositoryInterface {

		return new Repository();
	},
);
