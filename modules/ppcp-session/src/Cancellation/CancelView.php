<?php

/**
 * Renders the cancel view for the order on the checkout.
 *
 * @package WooCommerce\PayPalCommerce\Session\Cancellation
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Session\Cancellation;

use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
use WooCommerce\PayPalCommerce\WcGateway\FundingSource\FundingSourceRenderer;
/**
 * Class CancelView
 */
class CancelView
{
    /**
     * The settings provider.
     *
     * @var SettingsProvider
     */
    protected $settings_provider;
    /**
     * The funding source renderer.
     *
     * @var FundingSourceRenderer
     */
    protected $funding_source_renderer;
    /**
     * CancelView constructor.
     *
     * @param SettingsProvider      $settings_provider The settings provider.
     * @param FundingSourceRenderer $funding_source_renderer The funding source renderer.
     */
    public function __construct(SettingsProvider $settings_provider, FundingSourceRenderer $funding_source_renderer)
    {
        $this->settings_provider = $settings_provider;
        $this->funding_source_renderer = $funding_source_renderer;
    }
    /**
     * Renders the cancel link.
     *
     * @param string      $url The URL.
     * @param string|null $funding_source The ID of the funding source, such as 'venmo'.
     */
    public function render_session_cancellation(string $url, ?string $funding_source): string
    {
        ob_start();
        ?>
		<p id="ppcp-cancel"
			class="has-text-align-center ppcp-cancel"
		>
			<?php 
        $name = $funding_source ? $this->funding_source_renderer->render_name($funding_source) : $this->settings_provider->paypal_gateway_title();
        printf(
            // translators: %3$ is funding source like "PayPal" or "Venmo", other placeholders are html tags for a link.
            esc_html__('You are currently paying with %3$s. %4$s%1$sChoose another payment method%2$s.', 'woocommerce-paypal-payments'),
            '<a href="' . esc_url($url) . '">',
            '</a>',
            esc_html($name),
            '<br/>'
        );
        ?>
		</p>
		<?php 
        return (string) ob_get_clean();
    }
}
