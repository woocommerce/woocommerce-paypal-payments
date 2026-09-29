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
use WooCommerce\PayPalCommerce\Settings\Data\PayLaterMessagingSettings;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Helper\SettingsStatus;

/**
 * Registers a Pay Later messaging block and a Smart Buttons block for the Single Product
 * page and auto-inserts them into the block-theme Single Product template, where the classic
 * `woocommerce_*` hooks the SDK renders through do not fire: messaging below the product
 * price (see messaging_placement()), buttons after the add-to-cart block.
 *
 * This lives in the always-loaded blocks module rather than a module of its own; the blocks
 * simply ship with the plugin and appear where the merchant's "Product" button / Pay Later
 * messaging placement settings enable them.
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
	 * The add-to-cart block variants the blocks are auto-inserted after.
	 */
	public const ADD_TO_CART_ANCHORS = array( 'woocommerce/add-to-cart-form', 'woocommerce/add-to-cart-with-options' );

	/**
	 * The product price block, the messaging block's default anchor.
	 */
	public const PRICE_ANCHOR = 'woocommerce/product-price';

	/**
	 * Where the messaging block is auto-inserted relative to its anchor by default.
	 */
	public const MESSAGING_DEFAULT_POSITION = 'after';

	/**
	 * The relative positions the Block Hooks API accepts.
	 */
	private const BLOCK_HOOK_POSITIONS = array( 'before', 'after', 'first_child', 'last_child' );

	/**
	 * The WooCommerce block that renders the classic PHP template inside a block template.
	 */
	private const LEGACY_TEMPLATE_BLOCK = 'woocommerce/legacy-template';

	/**
	 * The action hook the classic product renderer is redirected to when the blocks render the
	 * page: a name nothing fires on its own, so the classic render path stands down and the
	 * blocks are the sole product renderer. Fired from the original hook only when a page
	 * builder replaces the block template (see restore_classic_render_for_builders()).
	 */
	private const NEUTRALIZED_RENDER_HOOK = 'ppcp_product_blocks_render_noop';

	/**
	 * The renderer hook the classic path was redirected away from on this request, or null
	 * when it was not redirected.
	 *
	 * @var string|null
	 */
	private static $neutralized_from = null;

	/**
	 * Whether the neutralized callbacks were already bridged back onto the original hook.
	 *
	 * @var bool
	 */
	private static $classic_render_restored = false;

	/**
	 * Whether the product Pay Later messaging surface is enabled, per the merchant's
	 * "Product" Pay Later messaging placement setting.
	 *
	 * @param SettingsStatus $settings_status The settings status helper.
	 */
	public static function is_messaging_enabled( SettingsStatus $settings_status ): bool {
		return $settings_status->is_pay_later_messaging_enabled_for_location( 'product' );
	}

	/**
	 * Whether the product Smart Buttons surface is enabled, per the merchant's "Product"
	 * button placement setting.
	 *
	 * @param SettingsStatus $settings_status The settings status helper.
	 */
	public static function is_buttons_enabled( SettingsStatus $settings_status ): bool {
		return $settings_status->is_smart_button_enabled_for_location( 'product' );
	}

	/**
	 * Where the messaging block is auto-inserted into the Single Product template: directly
	 * below the product price by default.
	 *
	 * @return array{anchor: array<int, string>, position: string}
	 */
	public static function messaging_placement(): array {
		$default = array(
			'anchor'   => array( self::PRICE_ANCHOR ),
			'position' => self::MESSAGING_DEFAULT_POSITION,
		);

		/**
		 * Filters where the Pay Later messaging block is auto-inserted into block-theme
		 * Single Product templates.
		 *
		 * @param array $placement {
		 *     @type string|string[] $anchor   The block type(s) to insert the messaging block next to.
		 *     @type string          $position One of before, after, first_child or last_child.
		 * }
		 */
		$placement = apply_filters(
			'woocommerce_paypal_payments_product_messages_block_placement',
			array(
				'anchor'   => self::PRICE_ANCHOR,
				'position' => self::MESSAGING_DEFAULT_POSITION,
			)
		);

		if ( ! is_array( $placement ) ) {
			return $default;
		}

		$anchor   = $placement['anchor'] ?? null;
		$anchors  = is_string( $anchor ) ? array( $anchor ) : $anchor;
		$position = $placement['position'] ?? null;

		if ( ! is_array( $anchors ) || ! in_array( $position, self::BLOCK_HOOK_POSITIONS, true ) ) {
			return $default;
		}

		$anchors = array_values(
			array_filter(
				$anchors,
				static function ( $block_type ): bool {
					return is_string( $block_type ) && '' !== $block_type;
				}
			)
		);

		if ( ! $anchors ) {
			return $default;
		}

		return array(
			'anchor'   => $anchors,
			'position' => $position,
		);
	}

	/**
	 * Whether a messaging insertion may be positioned against this anchor block.
	 *
	 * A price block only qualifies when it prices the product the template is about: the
	 * related-products and other loops in the template reuse the same block, and Block Hooks
	 * would otherwise put a message under each of their prices. Other anchors always qualify.
	 *
	 * @param mixed $parsed_anchor_block The parsed anchor block.
	 */
	public static function is_single_product_price_anchor( $parsed_anchor_block ): bool {
		if ( ! is_array( $parsed_anchor_block ) ) {
			return false;
		}

		if ( self::PRICE_ANCHOR !== ( $parsed_anchor_block['blockName'] ?? null ) ) {
			return true;
		}

		$attrs = is_array( $parsed_anchor_block['attrs'] ?? null ) ? $parsed_anchor_block['attrs'] : array();

		return ! empty( $attrs['isDescendentOfSingleProductTemplate'] ) && empty( $attrs['isDescendentOfQueryLoop'] );
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
	 * Wires the blocks: registration, auto-insertion and the block-theme dedup.
	 *
	 * Must be called on the `init` hook - block types register there, and resolving the
	 * country/settings collaborators is only safe once WooCommerce has booted.
	 *
	 * @param ContainerInterface $c The container.
	 */
	public static function register( ContainerInterface $c ): void {
		$messages_apply = $c->get( 'button.helper.messages-apply' );
		assert( $messages_apply instanceof MessagesApply );

		// Pay Later is country-restricted; the buttons block does not depend on it, so only
		// the messaging block is gated on the merchant's country.
		self::register_blocks( $c, $messages_apply->for_country() );

		// Auto-insert the blocks into block-theme Single Product templates. No-op on classic themes.
		$hooked_blocks_registrar = $c->get( 'blocks.product-hooked-blocks-registrar' );
		assert( $hooked_blocks_registrar instanceof HookedBlocksRegistrar );
		$hooked_blocks_registrar->register();

		// When the product page is rendered from a block template that places the blocks, they
		// are authoritative: redirect the classic v5/v6 product render hook to a name nothing
		// fires, so buttons/messaging are not also rendered by the classic path (which
		// CompatModule remaps to woocommerce_after_add_to_cart_form). Every other page - classic
		// themes, the Classic template block, templates without the blocks - keeps the classic
		// path. Enqueue is unaffected, so the SDK still loads and mounts into the block's wrapper.
		// Priority 20 wins over CompatModule's remap at priority 5. Registered here (on init,
		// before `wp`) so it is in place when the classic renderer reads the filter.
		add_filter(
			'woocommerce_paypal_payments_single_product_renderer_hook',
			static function ( $hook ) {
				if ( ! is_string( $hook ) || ! self::is_block_theme() ) {
					return $hook;
				}

				if ( ! self::template_renders_blocks( self::current_product_template_content() ) ) {
					return $hook;
				}

				self::$neutralized_from = $hook;

				return self::NEUTRALIZED_RENDER_HOOK;
			},
			20
		);

		// The decision above runs on `wp`, but a page builder (Elementor Pro, Divi Builder, ...)
		// can still replace the block template on `template_include`. The latest priority sees
		// the template that actually renders.
		add_filter(
			'template_include',
			static function ( $template ) {
				self::restore_classic_render_for_builders( $template );

				return $template;
			},
			PHP_INT_MAX
		);
	}

	/**
	 * Whether the template about to render is something other than core's block-template
	 * canvas, meaning a page builder replaced the block template and our blocks will not
	 * render.
	 *
	 * @param mixed $template The template path from `template_include`.
	 */
	public static function builder_overrode_template( $template ): bool {
		if ( ! is_string( $template ) || '' === $template ) {
			return false;
		}

		return wp_normalize_path( $template ) !== wp_normalize_path( ABSPATH . WPINC . '/template-canvas.php' );
	}

	/**
	 * Fires the neutralized classic render callbacks from the original hook when a page builder
	 * replaced the block template, so the product page does not lose buttons and messaging.
	 *
	 * Priority 30 matches the earliest classic product callback (messaging); the callbacks
	 * parked on the neutralized hook then run in their own priority order.
	 *
	 * @param mixed $template The template path from `template_include`.
	 */
	public static function restore_classic_render_for_builders( $template ): void {
		if ( null === self::$neutralized_from || self::$classic_render_restored || ! self::builder_overrode_template( $template ) ) {
			return;
		}

		self::$classic_render_restored = true;

		add_action(
			self::$neutralized_from,
			static function () {
				do_action( self::NEUTRALIZED_RENDER_HOOK );
			},
			30
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
				'ajax'                => array(
					'cart_script_params' => array( 'endpoint' => $script_params_endpoint ),
				),
				'config'              => $config_factory->from_settings( $paylater_settings ),
				'placementEnabled'    => self::is_messaging_enabled( $settings_status ),
				'payLaterSettingsUrl' => $settings_url,
				'settingsUrl'         => $settings_url,
				'isSdkV6Active'       => $c->has( 'sdk-v6.owns-current-page' ),
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
	 * Whether a block template's content renders the blocks, so the classic product render path
	 * must stand down.
	 *
	 * True when the template places a block explicitly or contains an add-to-cart or price
	 * anchor the blocks are auto-inserted next to. False when there is no block template, or it delegates
	 * to the classic PHP template through the Classic template block.
	 *
	 * @param string|null $content The block template content, or null when none applies.
	 */
	public static function template_renders_blocks( ?string $content ): bool {
		if ( null === $content || has_block( self::LEGACY_TEMPLATE_BLOCK, $content ) ) {
			return false;
		}

		foreach ( array_merge( array( self::BUTTONS_BLOCK, self::MESSAGING_BLOCK, self::PRICE_ANCHOR ), self::ADD_TO_CART_ANCHORS ) as $block_type ) {
			if ( has_block( $block_type, $content ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * The content of the block template WordPress will render for the current single product,
	 * resolved through the same hierarchy as locate_block_template().
	 *
	 * Called from the renderer-hook filter, which runs on `wp` - before `template_include`, so
	 * the resolved template is not yet available as a global.
	 *
	 * @return string|null The template content, or null outside a single product page or when
	 *                     no block template applies.
	 */
	private static function current_product_template_content(): ?string {
		if ( ! function_exists( 'is_product' ) || ! is_product() || ! function_exists( 'resolve_block_template' ) ) {
			return null;
		}

		$product_post = get_queried_object();
		if ( ! $product_post instanceof \WP_Post ) {
			return null;
		}

		$templates = (array) apply_filters(
			'single_template_hierarchy',
			array(
				'single-' . $product_post->post_type . '-' . $product_post->post_name . '.php',
				'single-' . $product_post->post_type . '.php',
				'single.php',
			)
		);

		$template = resolve_block_template( 'single', $templates, '' );

		return $template instanceof \WP_Block_Template ? (string) $template->content : null;
	}

	/**
	 * Whether the active theme is a block theme.
	 */
	private static function is_block_theme(): bool {
		return function_exists( 'wp_is_block_theme' ) && wp_is_block_theme();
	}
}
