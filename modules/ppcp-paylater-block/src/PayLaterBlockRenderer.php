<?php

/**
 * Defines the PayLaterBlockRenderer class.
 *
 * This file is responsible for rendering the Pay Later Messaging block.
 *
 * @package WooCommerce\PayPalCommerce\PayLaterBlock
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\PayLaterBlock;

use WooCommerce\PayPalCommerce\ApiClient\Helper\PartnerAttribution;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
/**
 * Class PayLaterBlockRenderer
 */
class PayLaterBlockRenderer
{
    /**
     * Renders the Pay Later Messaging block.
     *
     * @param array              $attributes The block attributes.
     * @param ContainerInterface $c The container.
     * @return string The rendered HTML.
     */
    public function render(array $attributes, ContainerInterface $c): string
    {
        if (\WooCommerce\PayPalCommerce\PayLaterBlock\PayLaterBlockModule::is_block_enabled($c->get('wcgateway.settings.status'))) {
            $partner_attribution = $c->get('api.helper.partner-attribution');
            assert($partner_attribution instanceof PartnerAttribution);
            $bn_code = $partner_attribution->get_bn_code();
            $html = '<div id="' . esc_attr($attributes['id'] ?? '') . '" class="ppcp-messages" data-partner-attribution-id="' . esc_attr($bn_code) . '"></div>';
            $processor = new \WP_HTML_Tag_Processor($html);
            if ($processor->next_tag('div')) {
                $layout = $attributes['layout'] ?? 'text';
                if ('flex' === $layout) {
                    $processor->set_attribute('data-pp-style-layout', 'flex');
                    $processor->set_attribute('data-pp-style-color', esc_attr($attributes['flexColor'] ?? ''));
                    $processor->set_attribute('data-pp-style-ratio', esc_attr($attributes['flexRatio'] ?? ''));
                } else {
                    $processor->set_attribute('data-pp-style-layout', 'text');
                    $processor->set_attribute('data-pp-style-logo-type', esc_attr($attributes['logo'] ?? ''));
                    $processor->set_attribute('data-pp-style-logo-position', esc_attr($attributes['position'] ?? ''));
                    $processor->set_attribute('data-pp-style-text-color', esc_attr($attributes['color'] ?? ''));
                    $processor->set_attribute('data-pp-style-text-size', esc_attr($attributes['size'] ?? ''));
                }
                if (($attributes['placement'] ?? 'auto') !== 'auto') {
                    $processor->set_attribute('data-pp-placement', esc_attr($attributes['placement']));
                }
            }
            $updated_html = $processor->get_updated_html();
            return sprintf('<div id="ppcp-paylater-message-block" %1$s>%2$s</div>', wp_kses_data(get_block_wrapper_attributes()), $updated_html);
        }
        return '';
    }
}
