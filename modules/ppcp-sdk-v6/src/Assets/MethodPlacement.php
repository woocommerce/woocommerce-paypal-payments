<?php

/**
 * Where and how one wallet is placed on a classic page.
 *
 * Everything SdkV6Manager needs to treat the wallets as a list rather than as
 * named pairs of properties and methods. A third wallet is one more instance,
 * not another copy of six code sites.
 *
 * @package WooCommerce\PayPalCommerce\SdkV6\Assets
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\SdkV6\Assets;

use WooCommerce\PayPalCommerce\SdkV6\Helper\MethodRenderGate;
class MethodPlacement
{
    /**
     * The key this wallet's subtree takes in the script data.
     */
    public string $config_key;
    /**
     * The WC gateway this wallet is listed under on classic checkout.
     */
    public string $gateway_id;
    /**
     * The id of the container its button renders into as its own row.
     */
    public string $wrapper_id;
    /**
     * The third-party script the frontend loads for this wallet.
     */
    public string $sdk_url;
    public MethodRenderGate $config;
    /**
     * Not reached through $config: styles() is deliberately absent from the
     * MethodRenderGate contract, because the return shapes differ (Apple's
     * borderRadius is a CSS length, Google's an integer) and a shared signature
     * would widen to string|int. Holding it as a callable keeps each subclass's
     * own narrow return type intact.
     *
     * @var callable(string): array<string, string|int>
     */
    public $styles_resolver;
    /**
     * Memoizes is_method_gateway(), which walks every available gateway.
     */
    public ?bool $is_gateway = null;
    public function __construct(string $config_key, string $gateway_id, string $wrapper_id, string $sdk_url, MethodRenderGate $config, callable $styles)
    {
        $this->config_key = $config_key;
        $this->gateway_id = $gateway_id;
        $this->wrapper_id = $wrapper_id;
        $this->sdk_url = $sdk_url;
        $this->config = $config;
        $this->styles_resolver = $styles;
    }
    /**
     * This wallet's button styling for a page context.
     *
     * @return array<string, string|int>
     */
    public function styles(string $context): array
    {
        return ($this->styles_resolver)($context);
    }
}
