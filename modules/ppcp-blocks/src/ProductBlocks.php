<?php
/**
 * Single Product PayPal buttons & Pay Later messaging blocks.
 *
 * @package WooCommerce\PayPalCommerce\Blocks
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Blocks;

use WooCommerce\PayPalCommerce\Assets\AssetGetter;
use WooCommerce\PayPalCommerce\Button\Endpoint\CartScriptParamsEndpoint;
use WooCommerce\PayPalCommerce\Button\Helper\MessagesApply;
use WooCommerce\PayPalCommerce\PayLaterConfigurator\Factory\ConfigFactory;
use WooCommerce\PayPalCommerce\PayLaterWCBlocks\HookedBlocksRegistrar;
use WooCommerce\PayPalCommerce\Settings\Data\PayLaterMessagingSettings;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Helper\SettingsStatus;

/**
 * Registers a Pay Later messaging block and a Smart Buttons block for the Single Product
 * page and auto-inserts them into the block-theme Single Product template, where the classic
 * `woocommerce_*` hooks the SDK renders through do not fire.
 *
 * This lives in the always-loaded blocks module rather than a module of its own; the
 * `is_feature_enabled()` flag gates it.
 */
class ProductBlocks {

	/**
	 * The messaging block type.
	 */
	public const MESSAGING_BLOCK = 'woocommerce-paypal-payments/product-paylater-messages';

	/**
	 * The Smart Buttons block type.
	 */
	public const BUTTONS_BLOCK = 'woocommerce-paypal-payments/product-smart-buttons';

	/**
	 * The action hook the classic product renderer is redirected to on block themes: a name
	 * nothing ever fires, so the classic render path stands down and the blocks are the sole
	 * product renderer.
	 */
	private const NEUTRALIZED_RENDER_HOOK = 'ppcp_product_blocks_render_noop';

	/**
	 * Whether the Single Product blocks feature is enabled.
	 */
	public static function is_feature_enabled(): bool {
		return apply_filters(
			// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
			'woocommerce.feature-flags.woocommerce_paypal_payments.product_blocks_enabled',
			getenv( 'PCP_PRODUCT_BLOCKS' ) !== '0'
		);
	}

	/**
	 * Whether the product Pay Later messaging surface is enabled.
	 *
	 * @param SettingsStatus $settings_status The settings status helper.
	 */
	public static function is_messaging_enabled( SettingsStatus $settings_status ): bool {
		return self::is_feature_enabled() && $settings_status->is_pay_later_messaging_enabled_for_location( 'product' );
	}

	/**
	 * Whether the product Smart Buttons surface is enabled.
	 *
	 * @param SettingsStatus $settings_status The settings status helper.
	 */
	public static function is_buttons_enabled( SettingsStatus $settings_status ): bool {
		return self::is_feature_enabled() && $settings_status->is_smart_button_enabled_for_location( 'product' );
	}

	/**
	 * Whether the SDK v6 stack is rendering the current page.
	 *
	 * Per page, not per site. Mirrors PayLaterBlockModule::v6_owns_current_page().
	 *
	 * @param ContainerInterface $c The container.
	 */
	public static function v6_owns_current_page( ContainerInterface $c ): bool {
		if ( ! $c->has( 'sdk-v6.owns-current-page' ) ) {
			return false;
		}

		$owns_current_page = $c->get( 'sdk-v6.owns-current-page' );

		return $owns_current_page();
	}

	/**
	 * Wires the blocks: registration, auto-insertion and the block-theme dedup. Safe to call
	 * from the blocks module's run(); no-op when the feature flag is off.
	 *
	 * @param ContainerInterface $c The container.
	 */
	public static function register( ContainerInterface $c ): void {
		if ( ! self::is_feature_enabled() ) {
			return;
		}

		$messages_apply = $c->get( 'button.helper.messages-apply' );
		assert( $messages_apply instanceof MessagesApply );

		// Pay Later is country-restricted; the buttons block does not depend on it, so only
		// the messaging block is gated on the merchant's country.
		$messaging_available = $messages_apply->for_country();

		add_action(
			'init',
			static function () use ( $c, $messaging_available ): void {
				self::register_blocks( $c, $messaging_available );
			},
			20
		);

		// Auto-insert the blocks into block-theme Single Product templates. No-op on classic themes.
		$hooked_blocks_registrar = $c->get( 'blocks.product-hooked-blocks-registrar' );
		assert( $hooked_blocks_registrar instanceof HookedBlocksRegistrar );
		$hooked_blocks_registrar->register();

		// On a block theme the blocks are authoritative: redirect the classic v5/v6 product
		// render hook to a name nothing fires, so buttons/messaging are not also rendered by
		// the classic path (which CompatModule remaps to woocommerce_after_add_to_cart_form).
		// Enqueue is unaffected, so the SDK still loads and mounts into the block's wrapper.
		// Priority 20 wins over CompatModule's remap at priority 5. Classic themes, Elementor
		// and Divi are untouched.
		add_filter(
			'woocommerce_paypal_payments_single_product_renderer_hook',
			static function ( $hook ) {
				return self::is_block_theme() ? self::NEUTRALIZED_RENDER_HOOK : $hook;
			},
			20
		);
	}

	/**
	 * Registers the two blocks: editor scripts, localized data and server render callbacks.
	 *
	 * @param ContainerInterface $c                   The container.
	 * @param bool               $messaging_available Whether Pay Later messaging applies to the merchant's country.
	 */
	private static function register_blocks( ContainerInterface $c, bool $messaging_available ): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		$asset_getter = $c->get( 'blocks.asset_getter' );
		assert( $asset_getter instanceof AssetGetter );

		$asset_version = $c->get( 'ppcp.asset-version' );

		$settings_status = $c->get( 'wcgateway.settings.status' );
		assert( $settings_status instanceof SettingsStatus );

		$settings_provider = $c->get( 'settings.settings-provider' );
		assert( $settings_provider instanceof SettingsProvider );

		$script_params_endpoint = \WC_AJAX::get_endpoint( CartScriptParamsEndpoint::ENDPOINT );
		$blocks_js_path         = $c->get( 'ppcp.path-to-plugin-folder' ) . 'modules/ppcp-blocks/resources/js/';
		$settings_url           = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway' );

		// Smart Buttons block.
		$buttons_handle = 'ppcp-product-smart-buttons-block';
		wp_register_script(
			$buttons_handle,
			$asset_getter->get_asset_url( 'ProductSmartButtonsBlock/product-smart-buttons-block.js' ),
			array(),
			$asset_version,
			true
		);
		wp_localize_script(
			$buttons_handle,
			'PcpProductSmartButtonsBlock',
			array(
				'ajax'             => array(
					'cart_script_params' => array( 'endpoint' => $script_params_endpoint ),
				),
				'placementEnabled' => self::is_buttons_enabled( $settings_status ),
				'settingsUrl'      => $settings_url,
				'isSdkV6Active'    => $c->has( 'sdk-v6.owns-current-page' ),
			)
		);

		register_block_type(
			$blocks_js_path . 'ProductSmartButtonsBlock',
			array(
				'render_callback' => static function ( array $attributes ) use ( $c ): string {
					$renderer = $c->get( 'blocks.product-buttons-renderer' );
					assert( $renderer instanceof ProductSmartButtonsRenderer );
					return $renderer->render( $attributes, $c );
				},
			)
		);

		// The messaging block's editor preview reads its style config from the Pay Later
		// configurator; without it (configurator disabled, or Pay Later not available in the
		// merchant's country) the messaging block is not registered. The buttons block above
		// does not depend on either.
		if ( ! $messaging_available || ! $c->has( 'paylater-configurator.factory.config' ) ) {
			return;
		}

		$config_factory = $c->get( 'paylater-configurator.factory.config' );
		assert( $config_factory instanceof ConfigFactory );

		$paylater_settings = $c->get( 'settings.data.paylater-messaging-settings' );
		assert( $paylater_settings instanceof PayLaterMessagingSettings );

		$messaging_handle = 'ppcp-product-paylater-block';
		wp_register_script(
			$messaging_handle,
			$asset_getter->get_asset_url( 'ProductPayLaterMessagesBlock/product-paylater-block.js' ),
			array(),
			$asset_version,
			true
		);
		wp_localize_script(
			$messaging_handle,
			'PcpProductPayLaterBlock',
			array(
				'ajax'                       => array(
					'cart_script_params' => array( 'endpoint' => $script_params_endpoint ),
				),
				'config'                     => $config_factory->from_settings( $paylater_settings ),
				'placementEnabled'           => self::is_messaging_enabled( $settings_status ),
				'payLaterDisabledByVaulting' => $settings_provider->pay_later_disabled_by_vaulting(),
				'payLaterSettingsUrl'        => $settings_url,
				'settingsUrl'                => $settings_url,
				'isSdkV6Active'              => $c->has( 'sdk-v6.owns-current-page' ),
			)
		);

		register_block_type(
			$blocks_js_path . 'ProductPayLaterMessagesBlock',
			array(
				'render_callback' => static function ( array $attributes ) use ( $c ): string {
					$renderer = $c->get( 'blocks.product-messaging-renderer' );
					assert( $renderer instanceof ProductPayLaterMessagesRenderer );
					return $renderer->render( $attributes, $c );
				},
			)
		);
	}

	/**
	 * Whether the active theme is a block theme.
	 */
	private static function is_block_theme(): bool {
		return function_exists( 'wp_is_block_theme' ) && wp_is_block_theme();
	}
}
