<?php
/**
 * The uninstall module services.
 *
 * @package WooCommerce\PayPalCommerce\Uninstall
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Uninstall;

use WooCommerce\PayPalCommerce\ApiClient\Repository\PayPalRequestIdRepository;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CardButtonGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayUponInvoice\PayUponInvoiceGateway;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
use WooCommerce\PayPalCommerce\Webhooks\Status\WebhookSimulation;
use WooCommerce\PayPalCommerce\Webhooks\WebhookRegistrar;

return array(
	'uninstall.ppcp-all-option-names'           => function( ContainerInterface $container ) : array {
		return array(
			$container->get( 'webhook.last-webhook-storage.key' ),
			PayPalRequestIdRepository::KEY,
			'woocommerce_ppcp-is_pay_later_settings_migrated',
			'woocommerce_' . PayPalGateway::ID . '_settings',
			'woocommerce_' . CreditCardGateway::ID . '_settings',
			'woocommerce_' . PayUponInvoiceGateway::ID . '_settings',
			'woocommerce_' . CardButtonGateway::ID . '_settings',
			Settings::KEY,
			'woocommerce-ppcp-version',
			WebhookSimulation::OPTION_ID,
			WebhookRegistrar::KEY,
		);
	},

	'uninstall.ppcp-all-scheduled-action-names' => function( ContainerInterface $container ) : array {
		return array(
			'woocommerce_paypal_payments_check_pui_payment_captured',
			'woocommerce_paypal_payments_check_saved_payment',
		);
	},
);
