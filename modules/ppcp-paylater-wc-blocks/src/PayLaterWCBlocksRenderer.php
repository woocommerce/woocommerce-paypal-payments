<?php

/**
 * The Pay Later WooCommerce Blocks Renderer.
 *
 * @package WooCommerce\PayPalCommerce\PayLaterWCBlocks
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\PayLaterWCBlocks;

use WooCommerce\PayPalCommerce\ApiClient\Helper\PartnerAttribution;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
/**
 * Class PayLaterWCBlocksRenderer
 */
class PayLaterWCBlocksRenderer
{
    /**
     * The placement.
     *
     * @var string
     */
    private $placement;
    /**
     * The layout.
     *
     * @var string
     */
    private $layout;
    /**
     * The position.
     *
     * @var string
     */
    private $position;
    /**
     * The logo.
     *
     * @var string
     */
    private $logo;
    /**
     * The text size.
     *
     * @var string
     */
    private $text_size;
    /**
     * The color.
     *
     * @var string
     */
    private $color;
    /**
     * The flex color.
     *
     * @var string
     */
    private $flex_color;
    /**
     * The flex ratio.
     *
     * @var string
     */
    private $flex_ratio;
    /**
     * PayLaterWCBlocksRenderer constructor.
     *
     * @param array $config The configuration.
     */
    public function __construct(array $config)
    {
        $this->placement = $config['placement'] ?? '';
        $this->layout = $config['layout'] ?? 'text';
        $this->position = $config['position'] ?? '';
        $this->logo = $config['logo'] ?? '';
        $this->text_size = $config['text_size'] ?? '';
        $this->color = $config['color'] ?? '';
        $this->flex_color = $config['flex_color'] ?? '';
        $this->flex_ratio = $config['flex_ratio'] ?? '';
    }
    /**
     * Renders the WC Pay Later Messaging blocks.
     *
     * @param array              $attributes The block attributes.
     * @param string             $location The location of the block.
     * @param ContainerInterface $c The container.
     * @return string|void
     */
    public function render(array $attributes, string $location, ContainerInterface $c)
    {
        if (\WooCommerce\PayPalCommerce\PayLaterWCBlocks\PayLaterWCBlocksModule::is_placement_enabled($c->get('wcgateway.settings.status'), $location)) {
            $partner_attribution = $c->get('api.helper.partner-attribution');
            assert($partner_attribution instanceof PartnerAttribution);
            $bn_code = $partner_attribution->get_bn_code();
            $html = '<div id="' . esc_attr($attributes['ppcpId'] ?? '') . '" class="ppcp-messages" data-partner-attribution-id="' . esc_attr($bn_code) . '"></div>';
            $processor = new \WP_HTML_Tag_Processor($html);
            if ($processor->next_tag('div')) {
                $processor->set_attribute('data-block-name', esc_attr($attributes['blockId'] ?? ''));
                $processor->set_attribute('class', 'ppcp-messages');
                $processor->set_attribute('data-partner-attribution-id', $bn_code);
                if ($this->layout === 'flex') {
                    $processor->set_attribute('data-pp-style-layout', 'flex');
                    $processor->set_attribute('data-pp-style-color', esc_attr($this->flex_color));
                    $processor->set_attribute('data-pp-style-ratio', esc_attr($this->flex_ratio));
                } else {
                    $processor->set_attribute('data-pp-style-layout', 'text');
                    $processor->set_attribute('data-pp-style-logo-type', esc_attr($this->logo));
                    $processor->set_attribute('data-pp-style-logo-position', esc_attr($this->position));
                    $processor->set_attribute('data-pp-style-text-color', esc_attr($this->color));
                    $processor->set_attribute('data-pp-style-text-size', esc_attr($this->text_size));
                }
                $processor->set_attribute('data-pp-placement', esc_attr($this->placement));
            }
            return $processor->get_updated_html();
        }
    }
}
