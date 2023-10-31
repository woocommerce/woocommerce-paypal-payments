<?php
/**
 * The endpoint for returning the PayPal SDK Script parameters for the current cart.
 *
 * @package WooCommerce\PayPalCommerce\Button\Endpoint
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Button\Endpoint;

use Psr\Log\LoggerInterface;
use Throwable;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Money;
use WooCommerce\PayPalCommerce\Button\Assets\SmartButton;

/**
 * Class CartScriptParamsEndpoint.
 */
class CartScriptParamsEndpoint implements EndpointInterface {


	const ENDPOINT = 'ppc-cart-script-params';

	/**
	 * The SmartButton.
	 *
	 * @var SmartButton
	 */
	private $smart_button;

	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * CartScriptParamsEndpoint constructor.
	 *
	 * @param SmartButton     $smart_button he SmartButton.
	 * @param LoggerInterface $logger The logger.
	 */
	public function __construct(
		SmartButton $smart_button,
		LoggerInterface $logger
	) {
		$this->smart_button = $smart_button;
		$this->logger       = $logger;
	}

	/**
	 * Returns the nonce.
	 *
	 * @return string
	 */
	public static function nonce(): string {
		return self::ENDPOINT;
	}

	/**
	 * Handles the request.
	 *
	 * @return bool
	 */
	public function handle_request(): bool {
		try {
			if ( is_callable( 'wc_maybe_define_constant' ) ) {
				wc_maybe_define_constant( 'WOOCOMMERCE_CART', true );
			}

			$script_data = $this->smart_button->script_data();

			$total = (float) WC()->cart->get_total( 'numeric' );

			// Shop settings.
			$base_location     = wc_get_base_location();
			$shop_country_code = $base_location['country'] ?? '';
			$currency_code     = get_woocommerce_currency();

			wp_send_json_success(
				array(
					'url_params'    => $script_data['url_params'],
					'button'        => $script_data['button'],
					'messages'      => $script_data['messages'],
					'amount'        => WC()->cart->get_total( 'raw' ),

					'total'         => $total,
					'total_str'     => ( new Money( $total, $currency_code ) )->value_str(),
					'currency_code' => $currency_code,
					'country_code'  => $shop_country_code,
				)
			);

			return true;
		} catch ( Throwable $error ) {
			$this->logger->error( "CartScriptParamsEndpoint execution failed. {$error->getMessage()} {$error->getFile()}:{$error->getLine()}" );

			wp_send_json_error();
			return false;
		}
	}
}
