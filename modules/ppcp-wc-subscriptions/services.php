<?php
/**
 * The services
 *
 * @package WooCommerce\PayPalCommerce\WcSubscriptions
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcSubscriptions;

use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcSubscriptions\Endpoint\SubscriptionChangePaymentMethod;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\FreeTrialSubscriptionHelper;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\RealTimeAccountUpdaterHelper;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\SubscriptionHelper;
use WooCommerce\PayPalCommerce\WcSubscriptions\Service\ChangePaymentMethod;
use WooCommerce\PayPalCommerce\WcSubscriptions\VaultV2\ChangePaymentMethodVaultV2;
use WooCommerce\PayPalCommerce\WcSubscriptions\VaultV2\DisplaySavedPaymentTokens;
use WooCommerce\PayPalCommerce\WcSubscriptions\VaultV2\VaultedPayPalEmail;

return array(
	'wc-subscriptions.helper'                            => static function ( ContainerInterface $container ): SubscriptionHelper {
		return new SubscriptionHelper();
	},
	'wc-subscriptions.helpers.real-time-account-updater' => static function ( ContainerInterface $container ): RealTimeAccountUpdaterHelper {
		return new RealTimeAccountUpdaterHelper();
	},
	'wc-subscriptions.renewal-handler'                   => static function ( ContainerInterface $container ): RenewalHandler {
		$logger                = $container->get( 'woocommerce.logger.woocommerce' );
		$repository            = $container->get( 'vaulting.repository.payment-token' );
		$endpoint              = $container->get( 'api.endpoint.order' );
		$purchase_unit_factory = $container->get( 'api.factory.purchase-unit' );
		$payer_factory         = $container->get( 'api.factory.payer' );
		$environment           = $container->get( 'settings.environment' );
		$settings                      = $container->get( 'wcgateway.settings' );
		$authorized_payments_processor = $container->get( 'wcgateway.processor.authorized-payments' );
		$funding_source_renderer       = $container->get( 'wcgateway.funding-source.renderer' );
		return new RenewalHandler(
			$logger,
			$repository,
			$endpoint,
			$purchase_unit_factory,
			$container->get( 'api.factory.shipping-preference' ),
			$payer_factory,
			$environment,
			$settings,
			$authorized_payments_processor,
			$funding_source_renderer,
			$container->get( 'wc-subscriptions.helpers.real-time-account-updater' ),
			$container->get( 'wc-subscriptions.helper' ),
			$container->get( 'api.endpoint.payment-tokens' ),
			$container->get( 'vaulting.wc-payment-tokens' ),
			$container->get( 'wcgateway.builder.experience-context' )
		);
	},
	'wc-subscriptions.endpoint.subscription-change-payment-method' => static function ( ContainerInterface $container ): SubscriptionChangePaymentMethod {
		return new SubscriptionChangePaymentMethod(
			$container->get( 'button.request-data' )
		);
	},
	'wc-subscriptions.change-payment-method'             => static function ( ContainerInterface $container ): ChangePaymentMethod {
		return new ChangePaymentMethod(
			$container->get( 'button.helper.context' )
		);
	},
	'wc-subscriptions.free-trial-subscription-helper'    => static function ( ContainerInterface $container ): FreeTrialSubscriptionHelper {
		return new FreeTrialSubscriptionHelper();
	},
	'wc-subscriptions.vault-v2.display-saved-payment-tokens' => static function ( ContainerInterface $container ): DisplaySavedPaymentTokens {
		return new DisplaySavedPaymentTokens(
			$container->get( 'wcgateway.settings' ),
			$container->get( 'wc-subscriptions.helper' )
		);
	},
	'wc-subscriptions.vault-v2.change-payment-method'    => static function ( ContainerInterface $container ): ChangePaymentMethodVaultV2 {
		return new ChangePaymentMethodVaultV2(
			$container->get( 'button.helper.context' )
		);
	},
	'wc-subscriptions.vault-v2.vaulted-paypal-email'     => static function ( ContainerInterface $container ): VaultedPayPalEmail {
		return new VaultedPayPalEmail(
			$container->get( 'api.endpoint.payment-tokens' ),
			$container->get( 'vaulting.repository.payment-token' ),
			$container->get( 'woocommerce.logger.woocommerce' )
		);
	},
	'wc-subscriptions.subscription_mode'                 => static function ( ContainerInterface $container ) {
		$subscription_helper = $container->get( 'wc-subscriptions.helper' );
		assert( $subscription_helper instanceof SubscriptionHelper );

		$settings_provider = $container->get( 'settings.settings-provider' );
		assert( $settings_provider instanceof SettingsProvider );

		if ( ! $subscription_helper->plugin_is_active() ) {
			return null;
		}

		$vaulting                = $settings_provider->save_paypal_and_venmo();
		$subscription_mode_value = $vaulting ? SubscriptionHelper::SUBSCRIPTION_MODE_VALUE_VAULTING : SubscriptionHelper::SUBSCRIPTION_MODE_VALUE_SUBSCRIPTIONS;

		/**
		 * Allows disabling the subscription mode when using the new settings UI.
		 *
		 * @returns bool true if the subscription mode should be disabled, false otherwise (default is false).
		 */
		$subscription_mode_disabled = (bool) apply_filters( 'woocommerce_paypal_payments_subscription_mode_disabled', false );
		return $subscription_mode_disabled ? SubscriptionHelper::SUBSCRIPTION_MODE_VALUE_DISABLED : $subscription_mode_value;
	},
);
