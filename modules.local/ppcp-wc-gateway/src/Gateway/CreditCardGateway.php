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
//phpcs:disable Inpsyde.CodeQuality.NoAccessors.NoGetter
//phpcs:disable Inpsyde.CodeQuality.ReturnTypeDeclaration.NoReturnType
class CreditCardGateway extends PayPalGateway
{
    public const ID = 'ppcp-credit-card-gateway';

    private $moduleUrl;
    public function __construct(
        SettingsRenderer $settingsRenderer,
        OrderProcessor $orderProcessor,
        AuthorizedPaymentsProcessor $authorizedPayments,
        AuthorizeOrderActionNotice $notice,
        ContainerInterface $config,
        string $moduleUrl
    ) {

        $this->id = self::ID;
        $this->orderProcessor = $orderProcessor;
        $this->authorizedPayments = $authorizedPayments;
        $this->notice = $notice;
        $this->settingsRenderer = $settingsRenderer;
        $this->config = $config;
        if (
            defined('PPCP_FLAG_SUBSCRIPTION')
            && PPCP_FLAG_SUBSCRIPTION
            && $this->config->has('vault_enabled')
            && $this->config->get('vault_enabled')
        ) {
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

        $this->method_title = __('PayPal Credit Card Processing', 'woocommerce-paypal-commerce-gateway');
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

        $this->moduleUrl = $moduleUrl;
    }

    public function init_form_fields()
    {
        $this->form_fields = [
            'enabled' => [
                'title' => __('Enable/Disable', 'woocommerce-paypal-commerce-gateway'),
                'type' => 'checkbox',
                'label' => __('Enable Credit Card Payments', 'woocommerce-paypal-commerce-gateway'),
                'default' => 'no',
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

    public function get_title()
    {

        if (is_admin()) {
            return parent::get_title();
        }
        $title = parent::get_title();
        $icons = $this->config->has('card_icons') ? (array) $this->config->get('card_icons') : [];
        if (empty($icons)) {
            return $title;
        }

        $titleOptions = $this->cardLabels();
        $images = array_map(
            function (string $type) use ($titleOptions): string {
                return '<img
                 title="' . esc_attr($titleOptions[$type]) . '"
                 src="' . esc_url($this->moduleUrl) . '/assets/images/' . esc_attr($type) . '.svg"
                 class="ppcp-card-icon"
                > ';
            },
            $icons
        );
        return $title . implode('', $images);
    }

    private function cardLabels(): array
    {
        return [
            'visa' => _x(
                'Visa',
                'Name of credit card',
                'woocommerce-paypal-commerce-gateway'
            ),
            'mastercard' => _x(
                'Mastercard',
                'Name of credit card',
                'woocommerce-paypal-commerce-gateway'
            ),
            'amex' => _x(
                'American Express',
                'Name of credit card',
                'woocommerce-paypal-commerce-gateway'
            ),
            'discover' => _x(
                'Discover',
                'Name of credit card',
                'woocommerce-paypal-commerce-gateway'
            ),
            'jcb' => _x(
                'JCB',
                'Name of credit card',
                'woocommerce-paypal-commerce-gateway'
            ),
            'elo' => _x(
                'Elo',
                'Name of credit card',
                'woocommerce-paypal-commerce-gateway'
            ),
            'hiper' => _x(
                'Hiper',
                'Name of credit card',
                'woocommerce-paypal-commerce-gateway'
            ),
        ];
    }
}
