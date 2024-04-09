<?php
/**
 * The Pay Later WooCommerce Blocks integration.
 *
 * @package WooCommerce\PayPalCommerce\PayLaterWCBlocks
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\PayLaterWCBlocks;

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;

/**
 * Class for integrating with WooCommerce Blocks
 */
class PayLaterWCBlocksIntegration implements IntegrationInterface {

	/**
	 * The URL of the Pay Later WooCommerce Blocks plugin.
	 *
	 * @var string
	 */
	protected $paylater_wc_blocks_url;

	/**
	 * The version of the Pay Later WooCommerce Blocks plugin.
	 *
	 * @var string
	 */
	protected $ppcp_asset_version;

	/**
	 * Constructor
	 *
	 * @param string $paylater_wc_blocks_url The URL of the Pay Later WooCommerce Blocks plugin.
	 * @param string $ppcp_asset_version The version of the Pay Later WooCommerce Blocks plugin.
	 */
	public function __construct( string $paylater_wc_blocks_url, string $ppcp_asset_version ) {
		$this->paylater_wc_blocks_url = $paylater_wc_blocks_url;
		$this->ppcp_asset_version     = $ppcp_asset_version;
	}

	/**
	 * The name of the integration.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'ppcp-paylater-wc-blocks';
	}

	/**
	 * The version of the integration.
	 *
	 * @return string
	 */
	public function get_version(): string {
		return $this->ppcp_asset_version;
	}

	/**
	 * When called invokes any initialization/setup for the integration.
	 *
	 * @return void
	 */
	public function initialize(): void {
		$this->register_paylater_wc_blocks_frontend_scripts();
		$this->register_paylater_wc_blocks_editor_scripts();
		$this->register_main_integration();
	}

	/**
	 * Registers the main JS files.
	 *
	 * @return void
	 */
	private function register_main_integration() : void {
		$cart_block_script_path     = 'assets/js/ppcp-cart-paylater-messages-block.js';
		$checkout_block_script_path = 'assets/js/ppcp-checkout-paylater-messages-block.js';
		$style_path                 = 'build/style-index.css';

		$cart_block_script_url     = $this->paylater_wc_blocks_url . $cart_block_script_path;
		$checkout_block_script_url = $this->paylater_wc_blocks_url . $checkout_block_script_path;

		$style_url = $this->paylater_wc_blocks_url . $style_path;

		$cart_block_script_asset_path     = $this->paylater_wc_blocks_url . 'assets/ppcp-cart-paylater-messages-block.asset.php';
		$checkout_block_script_asset_path = $this->paylater_wc_blocks_url . 'assets/ppcp-checkout-paylater-messages-block.asset.php';

		$cart_block_script_asset = file_exists( $cart_block_script_asset_path )
			? require $cart_block_script_asset_path
			: array(
				'dependencies' => array(),
				'version'      => $this->get_file_version( $cart_block_script_asset_path ),
			);

		$checkout_block_script_asset = file_exists( $checkout_block_script_asset_path )
			? require $checkout_block_script_asset_path
			: array(
				'dependencies' => array(),
				'version'      => $this->get_file_version( $checkout_block_script_asset_path ),
			);

		wp_register_script(
			'ppcp-cart-paylater-messages-block',
			$cart_block_script_url,
			$cart_block_script_asset['dependencies'],
			$cart_block_script_asset['version'],
			true
		);

		wp_register_script(
			'ppcp-checkout-paylater-messages-block',
			$checkout_block_script_url,
			$checkout_block_script_asset['dependencies'],
			$checkout_block_script_asset['version'],
			true
		);

		wp_set_script_translations(
			'ppcp-cart-paylater-messages-block',
			'woocommerce-paypal-payments',
			$this->paylater_wc_blocks_url . 'languages'
		);

		wp_set_script_translations(
			'ppcp-checkout-paylater-messages-block',
			'woocommerce-paypal-payments',
			$this->paylater_wc_blocks_url . 'languages'
		);
	}

	/**
	 * Returns an array of script handles to enqueue in the frontend context.
	 *
	 * @return string[]
	 */
	public function get_script_handles(): array {
		return array( 'ppcp-checkout-paylater-messages-block', 'ppcp-cart-paylater-messages-block', 'ppcp-cart-paylater-messages-block-frontend', 'ppcp-checkout-paylater-messages-block-frontend' );
	}

	/**
	 * Returns an array of script handles to enqueue in the editor context.
	 *
	 * @return string[]
	 */
	public function get_editor_script_handles(): array {
		return array( 'ppcp-cart-paylater-wc-blocks-editor', 'ppcp-checkout-paylater-wc-blocks-editor', 'ppcp-checkout-paylater-messages-block', 'ppcp-cart-paylater-messages-block' );
	}

	/**
	 * An array of key, value pairs of data made available to the block on the client side.
	 *
	 * @return array
	 */
	public function get_script_data(): array {

		return array(
			'ppcp-paylater-wc-blocks-active' => true,
		);
	}

	/**
	 * Registers the editor scripts.
	 *
	 * @return void
	 */
	public function register_paylater_wc_blocks_editor_scripts(): void {
		$cart_block_script_path           = 'assets/js/cart-paylater-messages-block.js';
		$checkout_block_script_path       = 'assets/js/checkout-paylater-messages-block.js';
		$cart_block_script_url            = $this->paylater_wc_blocks_url . $cart_block_script_path;
		$checkout_block_script_url        = $this->paylater_wc_blocks_url . $checkout_block_script_path;
		$cart_block_script_asset_path     = $this->paylater_wc_blocks_url . 'assets/cart-paylater-messages-block.asset.php';
		$checkout_block_script_asset_path = $this->paylater_wc_blocks_url . 'assets/checkout-paylater-messages-block.asset.php';

		$cart_block_script_asset = file_exists( $cart_block_script_asset_path )
			? require $cart_block_script_asset_path
			: array(
				'dependencies' => array(),
				'version'      => $this->get_file_version( $cart_block_script_asset_path ),
			);

		$checkout_block_script_asset = file_exists( $checkout_block_script_asset_path )
			? require $checkout_block_script_asset_path
			: array(
				'dependencies' => array(),
				'version'      => $this->get_file_version( $checkout_block_script_asset_path ),
			);

		wp_register_script(
			'ppcp-cart-paylater-wc-blocks-editor',
			$cart_block_script_url,
			$cart_block_script_asset['dependencies'],
			$cart_block_script_asset['version'],
			true
		);

		wp_register_script(
			'ppcp-checkout-paylater-wc-blocks-editor',
			$checkout_block_script_url,
			$checkout_block_script_asset['dependencies'],
			$checkout_block_script_asset['version'],
			true
		);

		wp_set_script_translations(
			'ppcp-cart-paylater-wc-blocks-editor',
			'woocommerce-paypal-payments',
			$this->paylater_wc_blocks_url . '/languages'
		);

		wp_set_script_translations(
			'ppcp-checkout-paylater-wc-blocks-editor',
			'woocommerce-paypal-payments',
			$this->paylater_wc_blocks_url . '/languages'
		);
	}

	/**
	 * Registers the frontend scripts.
	 *
	 * @return void
	 */
	public function register_paylater_wc_blocks_frontend_scripts(): void {
		$cart_block_script_path           = 'assets/js/cart-paylater-messages-block-frontend.js';
		$checkout_block_script_path       = 'assets/js/checkout-paylater-messages-block-frontend.js';
		$cart_block_script_url            = $this->paylater_wc_blocks_url . $cart_block_script_path;
		$checkout_block_script_url        = $this->paylater_wc_blocks_url . $checkout_block_script_path;
		$cart_block_script_asset_path     = $this->paylater_wc_blocks_url . 'assets/cart-paylater-messages-block-frontend.asset.php';
		$checkout_block_script_asset_path = $this->paylater_wc_blocks_url . 'assets/checkout-paylater-messages-block-frontend.asset.php';

		$cart_block_script_asset = file_exists( $cart_block_script_asset_path )
			? require $cart_block_script_asset_path
			: array(
				'dependencies' => array(),
				'version'      => $this->get_file_version( $cart_block_script_asset_path ),
			);

		$checkout_block_script_asset = file_exists( $checkout_block_script_asset_path )
			? require $checkout_block_script_asset_path
			: array(
				'dependencies' => array(),
				'version'      => $this->get_file_version( $checkout_block_script_asset_path ),
			);

		wp_register_script(
			'ppcp-cart-paylater-messages-block-frontend',
			$cart_block_script_url,
			$cart_block_script_asset['dependencies'],
			$cart_block_script_asset['version'],
			true
		);

		wp_register_script(
			'ppcp-checkout-paylater-messages-block-frontend',
			$checkout_block_script_url,
			$checkout_block_script_asset['dependencies'],
			$checkout_block_script_asset['version'],
			true
		);

		wp_set_script_translations(
			'ppcp-cart-paylater-messages-block-frontend',
			'woocommerce-paypal-payments',
			$this->paylater_wc_blocks_url . '/languages'
		);

		wp_set_script_translations(
			'ppcp-checkout-paylater-messages-block-frontend',
			'woocommerce-paypal-payments',
			$this->paylater_wc_blocks_url . '/languages'
		);
	}

	/**
	 * Get the file modified time as a cache buster if we're in dev mode.
	 *
	 * @param string $file Local path to the file.
	 * @return string The cache buster value to use for the given file.
	 */
	protected function get_file_version( string $file ): string {
		if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG && file_exists( $file ) ) {
			$filemtime = filemtime( $file );
			if ( $filemtime ) {
				return (string) $filemtime;
			}
		}
		return $this->get_version();
	}
}
