<?php

/**
 * Renders the Single Product Pay Later messaging placeholder.
 *
 * @package WooCommerce\PayPalCommerce\Blocks
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Blocks;

use WooCommerce\PayPalCommerce\ApiClient\Helper\PartnerAttribution;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
/**
 * Emits the `.ppcp-messages` placeholder that the PayPal SDK hydrates into a Pay Later
 * message on the single product page. The style is read from the merchant's `product`
 * Pay Later messaging settings, mirroring the cart/checkout block renderers - there is no
 * per-block style inspector because the placement is configured centrally in settings.
 *
 * No front-end script is emitted: the shared SDK boot (v5 MessageRenderer, v6
 * messages/renderer.js) scans the DOM for `.ppcp-messages` wrappers and renders into them.
 */
class ProductPayLaterMessagesRenderer
{
    /**
     * The messaging style, keyed by the same names the settings DTO exposes.
     *
     * @var array<string, string>
     */
    private $config;
    /**
     * @param array<string, string> $config The messaging style pulled from the product placement settings.
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }
    /**
     * Renders the messaging placeholder, or an empty string when the product placement is off.
     *
     * @param array<string, mixed> $attributes The block attributes.
     * @param ContainerInterface   $c          The container.
     * @return string The rendered HTML.
     */
    public function render(array $attributes, ContainerInterface $c): string
    {
        if (!\WooCommerce\PayPalCommerce\Blocks\ProductBlocks::is_messaging_enabled($c->get('wcgateway.settings.status'))) {
            return '';
        }
        $partner_attribution = $c->get('api.helper.partner-attribution');
        assert($partner_attribution instanceof PartnerAttribution);
        $bn_code = $partner_attribution->get_bn_code();
        $html = '<div id="' . esc_attr($attributes['ppcpId'] ?? '') . '" class="ppcp-messages" data-partner-attribution-id="' . esc_attr($bn_code) . '"></div>';
        $processor = new \WP_HTML_Tag_Processor($html);
        if ($processor->next_tag('div')) {
            // v6 styles text only. Coerced, not migrated, so flag-off restores the banner.
            // The flex branch omits `data-pp-style-logo-type`, which v6 keys on.
            $layout = \WooCommerce\PayPalCommerce\Blocks\ProductBlocks::v6_owns_current_page($c) ? 'text' : $this->config['layout'] ?? 'text';
            if ('flex' === $layout) {
                $processor->set_attribute('data-pp-style-layout', 'flex');
                $processor->set_attribute('data-pp-style-color', esc_attr($this->config['flex_color'] ?? ''));
                $processor->set_attribute('data-pp-style-ratio', esc_attr($this->config['flex_ratio'] ?? ''));
            } else {
                $processor->set_attribute('data-pp-style-layout', 'text');
                $processor->set_attribute('data-pp-style-logo-type', esc_attr($this->config['logo'] ?? ''));
                $processor->set_attribute('data-pp-style-logo-position', esc_attr($this->config['position'] ?? ''));
                $processor->set_attribute('data-pp-style-text-color', esc_attr($this->config['color'] ?? ''));
                $processor->set_attribute('data-pp-style-text-size', esc_attr($this->config['text_size'] ?? ''));
            }
            $processor->set_attribute('data-pp-placement', 'product');
        }
        return sprintf('<div %1$s>%2$s</div>', wp_kses_data(get_block_wrapper_attributes()), $processor->get_updated_html());
    }
}
