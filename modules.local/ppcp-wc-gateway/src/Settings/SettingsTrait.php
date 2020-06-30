<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\WcGateway\Settings;

use Inpsyde\PayPalCommerce\Onboarding\Environment;

trait SettingsTrait
{
    /**
     * @var Environment
     */
    private $environment;

    private function defaultFields(): array
    {
        $isSandbox = ($this->environment) ? $this->environment->currentEnvironmentIs(Environment::SANDBOX) : false;
        $sandbox = [
            'type' => 'ppcp_info',
            'title' => __('Sandbox'),
            'text' => ($isSandbox) ? __('You are currently in the sandbox mode. Click Reset if you want to change your mode.', 'woocommerce-paypal-commerce-gateway') : __('You are in production mode. Click Reset if you want to change your mode.', 'woocommerce-paypal-commerce-gateway'),
        ];
        return [
            'enabled' => [
                'title' => __('Enable/Disable', 'woocommerce-paypal-gateway'),
                'type' => 'checkbox',
                'label' => __('Enable PayPal Payments', 'woocommerce-paypal-gateway'),
                'default' => 'yes',
            ],
            'title' => [
                'title' => __('Title', 'woocommerce-paypal-gateway'),
                'type' => 'text',
                'description' => __(
                    'This controls the title which the user sees during checkout.',
                    'woocommerce-paypal-gateway'
                ),
                'default' => __('PayPal', 'woocommerce-paypal-gateway'),
                'desc_tip' => true,
            ],
            'description' => [
                'title' => __('Description', 'woocommerce-paypal-gateway'),
                'type' => 'text',
                'desc_tip' => true,
                'description' => __(
                    'This controls the description which the user sees during checkout.',
                    'woocommerce-paypal-gateway'
                ),
                'default' => __(
                    'Pay via PayPal; you can pay with your credit card if you don\'t have a PayPal account.',
                    'woocommerce-paypal-gateway'
                ),
            ],

            'account_settings' => [
                'title' => __('Account Settings', 'woocommerce-paypal-gateway'),
                'type' => 'title',
                'description' => '',
            ],
            'merchant_email' => [
                'title' => __('PayPal Email', 'woocommerce-paypal-gateway'),
                'type' => 'email',
                'description' => __(
                    'Please enter the email address with which you want to receive payments.',
                    'woocommerce-paypal-gateway'
                ),
                'default' => '',
                'desc_tip' => true,
            ],
            'sandbox_on' => $sandbox,
        ];
    }
}
