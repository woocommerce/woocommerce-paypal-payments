<?php
/**
 * The status report module.
 *
 * @package WooCommerce\PayPalCommerce\StatusReport
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\StatusReport;

use WooCommerce\PayPalCommerce\ApiClient\Authentication\Bearer;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\ApiClient\Helper\ReferenceTransactionStatus;
use WooCommerce\PayPalCommerce\ApiClient\Helper\DccApplies;
use WooCommerce\PayPalCommerce\Applepay\ApplePayGateway;
use WooCommerce\PayPalCommerce\Axo\Gateway\AxoGateway;
use WooCommerce\PayPalCommerce\Button\Helper\MessagesApply;
use WooCommerce\PayPalCommerce\Googlepay\GooglePayGateway;
use WooCommerce\PayPalCommerce\Settings\Data\GeneralSettings;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExecutableModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ServiceModule;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\SubscriptionHelper;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Webhook;
use WooCommerce\PayPalCommerce\Webhooks\WebhookEventStorage;

/**
 * Class StatusReportModule
 */
class StatusReportModule implements ServiceModule, ExecutableModule {
	use ModuleClassNameIdTrait;

	/**
	 * Transient key caching the live PayPal-side registered webhooks for the status page.
	 */
	private const REGISTERED_WEBHOOKS_TRANSIENT = 'ppcp-status-registered-webhooks';

	/**
	 * {@inheritDoc}
	 */
	public function services(): array {
		return require __DIR__ . '/../services.php';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param ContainerInterface $c A services container instance.
	 */
	public function run( ContainerInterface $c ): bool {
		add_action(
			'woocommerce_system_status_report',
			function () use ( $c ) {
				$settings_provider = $c->get( 'settings.settings-provider' );
				assert( $settings_provider instanceof SettingsProvider );

				$subscriptions_mode_settings = $c->get( 'wcgateway.settings.fields.subscriptions_mode' ) ?: array();

				/* @var bool $is_connected Whether onboarding is complete. */
				$is_connected = $c->get( 'settings.flag.is-connected' );

				/* @var Bearer $bearer The bearer. */
				$bearer = $c->get( 'api.bearer' );

				/* @var DccApplies $dcc_applies The ddc applies. */
				$dcc_applies = $c->get( 'api.helpers.dccapplies' );

				/* @var MessagesApply $messages_apply The messages apply. */
				$messages_apply = $c->get( 'button.helper.messages-apply' );

				/* @var SubscriptionHelper $subscription_helper The subscription helper class. */
				$subscription_helper = $c->get( 'wc-subscriptions.helper' );

				$last_webhook_storage = $c->get( 'webhook.last-webhook-storage' );
				assert( $last_webhook_storage instanceof WebhookEventStorage );

				$reference_transaction_status = $c->get( 'api.reference-transaction-status' );
				assert( $reference_transaction_status instanceof ReferenceTransactionStatus );

				/* @var Renderer $renderer The renderer. */
				$renderer = $c->get( 'status-report.renderer' );

				$had_ppec_plugin = is_array( get_option( 'woocommerce_ppec_paypal_settings' ) );

				$subscription_mode_options = $c->get( 'wcgateway.settings.fields.subscriptions_mode_options' );

				/* @var GeneralSettings $general_settings General plugin settings. */
				$general_settings = $c->get( 'settings.data.general' );

				// Feature flag convention.
				// phpcs:disable WordPress.NamingConventions.ValidHookName.UseUnderscores
				$items = array(
					array(
						'label'          => esc_html__( 'Onboarded', 'woocommerce-paypal-payments' ),
						'exported_label' => 'Onboarded',
						'description'    => esc_html__( 'Whether PayPal account is correctly configured or not.', 'woocommerce-paypal-payments' ),
						'value'          => $this->bool_to_html(
							$this->onboarded( $bearer, $is_connected )
						),
					),
					array(
						'label'          => esc_html__( 'Branded only', 'woocommerce-paypal-payments' ),
						'exported_label' => 'Branded only',
						'description'    => esc_html__( 'Whether the plugin is in Branded only mode or not.', 'woocommerce-paypal-payments' ),
						'value'          => $this->bool_to_html( $general_settings->own_brand_only() ),
					),
					array(
						'label'          => esc_html__( 'Shop country code', 'woocommerce-paypal-payments' ),
						'exported_label' => 'Shop country code',
						'description'    => esc_html__( 'Country / State value on Settings / General / Store Address.', 'woocommerce-paypal-payments' ),
						'value'          => $c->get( 'api.shop.country' ),
					),
					array(
						'label'          => esc_html__( 'WooCommerce currency supported', 'woocommerce-paypal-payments' ),
						'exported_label' => 'WooCommerce currency supported',
						'description'    => esc_html__( 'Whether PayPal supports the default store currency or not.', 'woocommerce-paypal-payments' ),
						'value'          => $this->bool_to_html(
							$c->get( 'api.shop.is-currency-supported' )
						),
					),
					array(
						'label'          => esc_html__( 'Advanced Card Processing available in country', 'woocommerce-paypal-payments' ),
						'exported_label' => 'Advanced Card Processing available in country',
						'description'    => esc_html__( 'Whether Advanced Card Processing is available in country or not.', 'woocommerce-paypal-payments' ),
						'value'          => $this->bool_to_html(
							$dcc_applies->for_country_currency()
						),
					),
					array(
						'label'          => esc_html__( 'Pay Later messaging available in country', 'woocommerce-paypal-payments' ),
						'exported_label' => 'Pay Later messaging available in country',
						'description'    => esc_html__( 'Whether Pay Later is available in country or not.', 'woocommerce-paypal-payments' ),
						'value'          => $this->bool_to_html(
							$messages_apply->for_country()
						),
					),
					array(
						'label'          => esc_html__( 'Webhook status', 'woocommerce-paypal-payments' ),
						'exported_label' => 'Webhook status',
						'description'    => esc_html__( 'Whether we received webhooks successfully.', 'woocommerce-paypal-payments' ),
						'value'          => $this->bool_to_html( ! $last_webhook_storage->is_empty() ),
					),
					array(
						'label'          => esc_html__( 'Webhook delivery host', 'woocommerce-paypal-payments' ),
						'exported_label' => 'Webhook delivery host',
						'description'    => esc_html__( 'Whether PayPal delivers webhooks to this site or to a different host.', 'woocommerce-paypal-payments' ),
						'value'          => $this->webhook_delivery_host_status( $this->registered_webhooks( $c, $is_connected ) ),
					),
					array(
						'label'          => esc_html__( 'PayPal Vault enabled', 'woocommerce-paypal-payments' ),
						'exported_label' => 'PayPal Vault enabled',
						'description'    => esc_html__( 'Whether vaulting option is enabled on Standard Payments settings or not.', 'woocommerce-paypal-payments' ),
						'value'          => $this->bool_to_html(
							$settings_provider->save_paypal_and_venmo()
						),
					),
					array(
						'label'          => esc_html__( 'ACDC Vault enabled', 'woocommerce-paypal-payments' ),
						'exported_label' => 'ACDC Vault enabled',
						'description'    => esc_html__( 'Whether vaulting option is enabled on Advanced Card Processing settings or not.', 'woocommerce-paypal-payments' ),
						'value'          => $this->bool_to_html(
							$settings_provider->save_card_details()
						),
					),
					array(
						'label'          => esc_html__( 'Logging enabled', 'woocommerce-paypal-payments' ),
						'exported_label' => 'Logging enabled',
						'description'    => esc_html__( 'Whether logging of plugin events and errors is enabled.', 'woocommerce-paypal-payments' ),
						'value'          => $this->bool_to_html(
							$settings_provider->enable_logging()
						),
					),
					array(
						'label'          => esc_html__( 'Reference Transactions', 'woocommerce-paypal-payments' ),
						'exported_label' => 'Reference Transactions',
						'description'    => esc_html__( 'Whether Reference Transactions are enabled for the connected account', 'woocommerce-paypal-payments' ),
						'value'          => $this->bool_to_html(
							$reference_transaction_status->reference_transaction_enabled()
						),
					),
					array(
						'label'          => esc_html__( 'Used PayPal Checkout plugin', 'woocommerce-paypal-payments' ),
						'exported_label' => 'Used PayPal Checkout plugin',
						'description'    => esc_html__( 'Whether the PayPal Checkout Gateway plugin was configured previously or not', 'woocommerce-paypal-payments' ),
						'value'          => $this->bool_to_html(
							$had_ppec_plugin
						),
					),
					array(
						'label'          => esc_html__( 'Subscriptions Mode', 'woocommerce-paypal-payments' ),
						'exported_label' => 'Subscriptions Mode',
						'description'    => esc_html__( 'Whether subscriptions are active and their mode.', 'woocommerce-paypal-payments' ),
						'value'          => $this->subscriptions_mode_text(
							$subscription_helper->plugin_is_active(),
							(string) $subscription_mode_options[ $settings_provider->save_paypal_and_venmo() ? 'vaulting_api' : 'subscriptions_api' ],
							$subscriptions_mode_settings
						),
					),
					array(
						'label'          => esc_html__( 'PayPal Shipping Callback', 'woocommerce-paypal-payments' ),
						'exported_label' => 'PayPal Shipping Callback',
						'description'    => esc_html__( 'Whether the "Require final confirmation on checkout" setting is disabled.', 'woocommerce-paypal-payments' ),
						'value'          => $this->bool_to_html(
							$settings_provider->enable_pay_now()
						),
					),
					array(
						'label'          => esc_html__( 'Apple Pay', 'woocommerce-paypal-payments' ),
						'exported_label' => 'Apple Pay',
						'description'    => esc_html__( 'Whether Apple Pay is enabled.', 'woocommerce-paypal-payments' ),
						'value'          => $this->bool_to_html(
							$settings_provider->is_method_enabled( ApplePayGateway::ID )
						),
					),
					array(
						'label'          => esc_html__( 'Google Pay', 'woocommerce-paypal-payments' ),
						'exported_label' => 'Google Pay',
						'description'    => esc_html__( 'Whether Google Pay is enabled.', 'woocommerce-paypal-payments' ),
						'value'          => $this->bool_to_html(
							$settings_provider->is_method_enabled( GooglePayGateway::ID )
						),
					),
					array(
						'label'          => esc_html__( 'Fastlane', 'woocommerce-paypal-payments' ),
						'exported_label' => 'Fastlane',
						'description'    => esc_html__( 'Whether Fastlane is enabled.', 'woocommerce-paypal-payments' ),
						'value'          => $this->bool_to_html(
							$settings_provider->is_method_enabled( AxoGateway::ID )
						),
					),
				);

				echo wp_kses_post(
					$renderer->render(
						esc_html__( 'WooCommerce PayPal Payments', 'woocommerce-paypal-payments' ),
						$items
					)
				);
			}
		);

		return true;
	}

	/**
	 * It returns the current onboarding status.
	 *
	 * @param Bearer $bearer       The bearer.
	 * @param bool   $is_connected Whether onboarding is complete.
	 * @return bool
	 */
	private function onboarded( Bearer $bearer, bool $is_connected ): bool {
		try {
			$token = $bearer->bearer();
		} catch ( RuntimeException $exception ) {
			return false;
		}

		return $is_connected && $token->is_valid();
	}

	/**
	 * Returns the text associated with the subscriptions mode status.
	 *
	 * @param bool   $is_plugin_active     Indicates if the WooCommerce Subscriptions plugin is active.
	 * @param string $subscriptions_mode   The subscriptions mode stored in settings.
	 * @param array  $field_settings       The subscriptions mode field settings.
	 * @return string
	 */
	private function subscriptions_mode_text( bool $is_plugin_active, string $subscriptions_mode, array $field_settings ): string {
		if ( ! $is_plugin_active || ! $field_settings || $subscriptions_mode === 'disable_paypal_subscriptions' ) {
			return 'Disabled';
		}

		if ( ! $subscriptions_mode ) {
			$subscriptions_mode = $field_settings['default'] ?? '';
		}

		// Return the options value or if it's missing from options the settings value.
		return $field_settings['options'][ $subscriptions_mode ] ?? $subscriptions_mode;
	}

	/**
	 * Converts the bool value to "yes" icon or dash.
	 *
	 * @param bool $value The value.
	 * @return string
	 */
	private function bool_to_html( bool $value ): string {
		return $value
			? '<mark class="yes"><span class="dashicons dashicons-yes"></span></mark>'
			: '<mark class="no">&ndash;</mark>';
	}

	/**
	 * Fetches the live PayPal-side registered webhooks, cached behind a short transient.
	 *
	 * The 'webhook.status.registered-webhooks' factory is non-shared and performs a live
	 * PayPal API call on every resolution, so the result is cached briefly to avoid an
	 * extra request each time the status page is rendered. Any failure degrades to an
	 * empty list so the status page never fatals.
	 *
	 * @param ContainerInterface $c            The services container.
	 * @param bool               $is_connected Whether onboarding is complete.
	 * @return Webhook[]
	 */
	private function registered_webhooks( ContainerInterface $c, bool $is_connected ): array {
		if ( ! $is_connected ) {
			return array();
		}

		$cached = get_transient( self::REGISTERED_WEBHOOKS_TRANSIENT );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		try {
			$webhooks = $c->get( 'webhook.status.registered-webhooks' );
		} catch ( \Throwable $exception ) {
			return array();
		}

		$webhooks = is_array( $webhooks )
			? array_values( array_filter( $webhooks, fn( $webhook ) => $webhook instanceof Webhook ) )
			: array();

		set_transient( self::REGISTERED_WEBHOOKS_TRANSIENT, $webhooks, 5 * MINUTE_IN_SECONDS );

		return $webhooks;
	}

	/**
	 * Reports whether PayPal's registered webhook points at this site.
	 *
	 * Compares each registered webhook's host against this site's host. When they
	 * differ, webhook events are being delivered elsewhere - typically a staging or dev
	 * clone connected to the same PayPal account - which the "Webhook status" row above
	 * cannot detect.
	 *
	 * @param Webhook[] $registered_webhooks The live PayPal-side registered webhooks.
	 * @return string
	 */
	private function webhook_delivery_host_status( array $registered_webhooks ): string {
		$site_host = $this->normalized_host( home_url() );

		$foreign_hosts = array();
		foreach ( $registered_webhooks as $webhook ) {
			if ( ! $webhook instanceof Webhook ) {
				continue;
			}

			$webhook_host = $this->normalized_host( $webhook->url() );
			if ( '' === $webhook_host ) {
				continue;
			}

			if ( $webhook_host === $site_host ) {
				return $this->bool_to_html( true );
			}

			$foreign_hosts[ $webhook_host ] = $webhook_host;
		}

		if ( array() === $foreign_hosts ) {
			return $this->bool_to_html( false );
		}

		return sprintf(
			'<mark class="error"><span class="dashicons dashicons-warning"></span> %s</mark>',
			esc_html(
				sprintf(
					/* translators: 1: host(s) receiving the webhooks, 2: this site's host. */
					__( 'Delivered to %1$s (this site: %2$s)', 'woocommerce-paypal-payments' ),
					implode( ', ', $foreign_hosts ),
					$site_host
				)
			)
		);
	}

	/**
	 * Extracts the lower-cased host from a URL, or an empty string when it cannot be parsed.
	 *
	 * @param string $url The URL to extract the host from.
	 * @return string
	 */
	private function normalized_host( string $url ): string {
		$host = wp_parse_url( $url, PHP_URL_HOST );

		return is_string( $host ) ? strtolower( $host ) : '';
	}
}
