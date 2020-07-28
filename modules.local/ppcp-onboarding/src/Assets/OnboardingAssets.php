<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Onboarding\Assets;

use Inpsyde\PayPalCommerce\ApiClient\Endpoint\PartnerReferrals;
use Inpsyde\PayPalCommerce\Onboarding\Endpoint\LoginSellerEndpoint;
use Inpsyde\PayPalCommerce\Onboarding\State;

class OnboardingAssets
{

    private $moduleUrl;
    private $state;
    private $loginSellerEndpoint;
    public function __construct(
        string $moduleUrl,
        State $state,
        LoginSellerEndpoint $loginSellerEndpoint
    ) {

        $this->moduleUrl = $moduleUrl;
        $this->state = $state;
        $this->loginSellerEndpoint = $loginSellerEndpoint;
    }

    public function register(): bool
    {

        $url = $this->moduleUrl . '/assets/css/onboarding.css';
        wp_register_style(
            'ppcp-onboarding',
            $url,
            [],
            1
        );
        $url = $this->moduleUrl . '/assets/js/settings.js';
        wp_register_script(
            'ppcp-settings',
            $url,
            [],
            1,
            true
        );
        if (!$this->shouldRenderOnboardingScript()) {
            return false;
        }

        $url = $this->moduleUrl . '/assets/js/onboarding.js';
        wp_register_script(
            'ppcp-onboarding',
            $url,
            ['jquery'],
            1,
            true
        );
        wp_localize_script(
            'ppcp-onboarding',
            'PayPalCommerceGatewayOnboarding',
            [
                'endpoint' => home_url(\WC_AJAX::get_endpoint(LoginSellerEndpoint::ENDPOINT)),
                'nonce' => wp_create_nonce($this->loginSellerEndpoint::nonce()),
                'error' => __(
                    'We could not properly onboard you. Please reload and try again.',
                    'woocommerce-paypal-commerce-gateway'
                ),
            ]
        );

        return true;
    }

    public function enqueue(): bool
    {
        wp_enqueue_style('ppcp-onboarding');
        wp_enqueue_script('ppcp-settings');
        if (! $this->shouldRenderOnboardingScript()) {
            return false;
        }

        wp_enqueue_script('ppcp-onboarding');
        return true;
    }

    private function shouldRenderOnboardingScript(): bool
    {
        // phpcs:disable Inpsyde.CodeQuality.VariablesName.SnakeCaseVar
        global $current_section;
        if ($current_section !== 'ppcp-gateway') {
            return false;
        }

        $shouldRender = $this->state->currentState() === State::STATE_PROGRESSIVE;
        return $shouldRender;
    }
}
