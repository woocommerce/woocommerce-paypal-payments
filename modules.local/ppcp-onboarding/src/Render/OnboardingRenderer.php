<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Onboarding\Render;

use Inpsyde\PayPalCommerce\ApiClient\Endpoint\PartnerReferrals;
use Inpsyde\PayPalCommerce\ApiClient\Exception\RuntimeException;

class OnboardingRenderer
{

    private $partnerReferrals;
    public function __construct(PartnerReferrals $partnerReferrals)
    {
        $this->partnerReferrals = $partnerReferrals;
    }

    public function render()
    {
        try {
            $url = add_query_arg(
                [
                    'displayMode' => 'minibrowser',
                ],
                $this->partnerReferrals->signupLink()
            );
            ?>
                    <a
                            target="_blank"
                            class="button-primary"
                            data-paypal-onboard-complete="onboardingCallback"
                            href="<?php echo esc_url($url); ?>"
                            data-paypal-button="true"
                    ><?php
                        esc_html_e(
                            'Sign up for PayPal',
                            'woocommerce-paypal-commerce-gateway'
                        );
                        ?></a>
                    <script
                            id="paypal-js"
                            src="https://www.sandbox.paypal.com/webapps/merchantboarding/js/lib/lightbox/partner.js"
                    ></script>
            <?php
        } catch (RuntimeException $exception) {
             esc_html_e(
                 'We could not properly connect to PayPal. Please reload the page to continue',
                 'woocommerce-paypal-commerce-gateway'
             );
        }
    }
}
