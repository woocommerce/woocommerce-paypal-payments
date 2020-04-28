<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Button;

use Dhii\Data\Container\ContainerInterface;
use Inpsyde\PayPalCommerce\AdminNotices\Renderer\Renderer;
use Inpsyde\PayPalCommerce\AdminNotices\Renderer\RendererInterface;
use Inpsyde\PayPalCommerce\AdminNotices\Repository\Repository;
use Inpsyde\PayPalCommerce\AdminNotices\Repository\RepositoryInterface;
use Inpsyde\PayPalCommerce\Button\Assets\DisabledSmartButton;
use Inpsyde\PayPalCommerce\Button\Assets\SmartButton;
use Inpsyde\PayPalCommerce\Button\Assets\SmartButtonInterface;
use Inpsyde\PayPalCommerce\Button\Endpoint\ApproveOrderEndpoint;
use Inpsyde\PayPalCommerce\Button\Endpoint\ChangeCartEndpoint;
use Inpsyde\PayPalCommerce\Button\Endpoint\CreateOrderEndpoint;
use Inpsyde\PayPalCommerce\Button\Endpoint\RequestData;
use Inpsyde\PayPalCommerce\Button\Exception\RuntimeException;

return [
    'admin-notices.renderer' => function(ContainerInterface $container) : RendererInterface {

        $repository = $container->get('admin-notices.repository');
        return new Renderer($repository);
    },
    'admin-notices.repository' => function(ContainerInterface $container) : RepositoryInterface {

        return new Repository();
    }
];
