<?php

/**
 * Manages the SDK v6 frontend assets.
 *
 * @package WooCommerce\PayPalCommerce\SdkV6\Assets
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\SdkV6\Assets;

use WooCommerce\PayPalCommerce\Assets\AssetGetter;
use WooCommerce\PayPalCommerce\WcGateway\Helper\Environment;
/**
 * Class SdkV6Manager
 */
class SdkV6Manager
{
    /**
     * The asset getter.
     *
     * @var AssetGetter
     */
    private AssetGetter $asset_getter;
    // @phpstan-ignore property.onlyWritten
    /**
     * The assets version.
     *
     * @var string
     */
    private string $version;
    // @phpstan-ignore property.onlyWritten
    /**
     * The environment object.
     *
     * @var Environment
     */
    private Environment $environment;
    // @phpstan-ignore property.onlyWritten
    /**
     * SdkV6Manager constructor.
     *
     * @param AssetGetter $asset_getter The asset getter.
     * @param string      $version The assets version.
     * @param Environment $environment The environment object.
     */
    public function __construct(AssetGetter $asset_getter, string $version, Environment $environment)
    {
        $this->asset_getter = $asset_getter;
        $this->version = $version;
        $this->environment = $environment;
    }
    /**
     * Enqueues scripts/styles.
     *
     * @return void
     */
    public function enqueue(): void
    {
    }
}
