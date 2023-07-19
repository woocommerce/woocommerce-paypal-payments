<?php
/**
 * Renders the cancel view for the order on the checkout.
 *
 * @package WooCommerce\PayPalCommerce\Session\Cancellation
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Session\Cancellation;

use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\FundingSource\FundingSourceRenderer;

/**
 * Class CancelView
 */
class CancelView {
	/**
	 * The settings.
	 *
	 * @var ContainerInterface
	 */
	protected $settings;

	/**
	 * The funding source renderer.
	 *
	 * @var FundingSourceRenderer
	 */
	protected $funding_source_renderer;

	/**
	 * CancelView constructor.
	 *
	 * @param ContainerInterface    $settings The settings.
	 * @param FundingSourceRenderer $funding_source_renderer The funding source renderer.
	 */
	public function __construct(
		ContainerInterface $settings,
		FundingSourceRenderer $funding_source_renderer
	) {
		$this->settings                = $settings;
		$this->funding_source_renderer = $funding_source_renderer;
	}

	/**
	 * Renders the cancel link.
	 *
	 * @param string      $url The URL.
	 * @param string|null $funding_source The ID of the funding source, such as 'venmo'.
	 */
	public function render_session_cancellation( string $url, ?string $funding_source ): string {
		ob_start();
		?>
		<p id="ppcp-cancel"
			class="has-text-align-center ppcp-cancel"
		>
			<?php
			$name = $funding_source ?
				$this->funding_source_renderer->render_name( $funding_source )
				: ( $this->settings->has( 'title' ) ? $this->settings->get( 'title' ) : __( 'PayPal', 'woocommerce-paypal-payments' ) );
			printf(
					// translators: %3$ is funding source like "PayPal" or "Venmo", other placeholders are html tags for a link.
				esc_html__(
					'You are currently paying with %3$s. %4$s%1$sChoose another payment method%2$s.',
					'woocommerce-paypal-payments'
				),
				'<a href="' . esc_url( $url ) . '">',
				'</a>',
				esc_html( $name ),
				'<br/>'
			);
			?>
		</p>
		<?php
		return (string) ob_get_clean();
	}
}
