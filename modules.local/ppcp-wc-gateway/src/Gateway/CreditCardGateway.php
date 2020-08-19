<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\WcGateway\Gateway;

use Inpsyde\PayPalCommerce\WcGateway\Notice\AuthorizeOrderActionNotice;
use Inpsyde\PayPalCommerce\WcGateway\Processor\AuthorizedPaymentsProcessor;
use Inpsyde\PayPalCommerce\WcGateway\Processor\OrderProcessor;
use Inpsyde\PayPalCommerce\WcGateway\Settings\SettingsRenderer;
use Psr\Container\ContainerInterface;

//phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
//phpcs:disable Inpsyde.CodeQuality.ArgumentTypeDeclaration.NoArgumentType
class CreditCardGateway extends PayPalGateway
{
    public const ID = 'ppcp-credit-card-gateway';

    public function __construct(
        SettingsRenderer $settingsRenderer,
        OrderProcessor $orderProcessor,
        AuthorizedPaymentsProcessor $authorizedPayments,
        AuthorizeOrderActionNotice $notice,
        ContainerInterface $config
    ) {

        $this->id = self::ID;
        $this->orderProcessor = $orderProcessor;
        $this->authorizedPayments = $authorizedPayments;
        $this->notice = $notice;
        $this->settingsRenderer = $settingsRenderer;
        $this->config = $config;
        if ($this->config->has('vault_enabled') && $this->config->get('vault_enabled')) {
            $this->supports = [
                'products',
                'subscriptions',
                'subscription_cancellation',
                'subscription_suspension',
                'subscription_reactivation',
                'subscription_amount_changes',
                'subscription_date_changes',
                'subscription_payment_method_change',
                'subscription_payment_method_change_customer',
                'subscription_payment_method_change_admin',
                'multiple_subscriptions',
            ];
        }

        $this->method_title = __('PayPal Credit Card', 'woocommerce-paypal-commerce-gateway');
        $this->method_description = __(
            'Provide your customers with the option to pay with credit card.',
            'woocommerce-paypal-commerce-gateway'
        );
        $this->title = $this->config->has('dcc_gateway_title') ?
            $this->config->get('dcc_gateway_title') : $this->method_title;
        $this->description = $this->config->has('dcc_gateway_description') ?
            $this->config->get('dcc_gateway_description') : $this->method_description;

        $this->init_form_fields();
        $this->init_settings();

        add_action(
            'woocommerce_update_options_payment_gateways_' . $this->id,
            [
                $this,
                'process_admin_options',
            ]
        );
    }

    public function init_form_fields()
    {
        $this->form_fields = [
            'enabled' => [
                'title' => __('Enable/Disable', 'woocommerce-paypal-commerce-gateway'),
                'type' => 'checkbox',
                'label' => __('Enable Credit Card Payments', 'woocommerce-paypal-commerce-gateway'),
                'default' => 'yes',
            ],
            'ppcp' => [
                'type' => 'ppcp',
            ],
        ];
    }

    public function generate_ppcp_html(): string
    {

        ob_start();
        $this->settingsRenderer->render(true);
        $content = ob_get_contents();
        ob_end_clean();
        return $content;
    }
}
