<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Onboarding\Render;


use Inpsyde\PayPalCommerce\ApiClient\Endpoint\PartnerReferrals;

class OnboardingRenderer
{

    private $partnerReferrals;
    public function __construct(PartnerReferrals $partnerReferrals)
    {
        $this->partnerReferrals = $partnerReferrals;
    }

    public function render() {
        $url = add_query_arg(
            [
                'displayMode'=>'minibrowser',
            ],
            $this->partnerReferrals->signupLink()
        );
        ?>
        <script>
            function onboardingCallback(authCode, sharedId) => {
                fetch(PayPalCommerceGatewayOnboarding.endpoint, {
                    method: 'POST',
                    headers: {
                        'content-type': 'application/json'
                    },
                    body: JSON.stringify({
                        authCode: authCode,
                        sharedId: sharedId,
                        nonce: PayPalCommerceGatewayOnboarding.nonce
                    })
                });
            }
        </script>
        <a target="_blank" data-paypal-onboard-complete="onboardingCallback" href="<?php echo $url; ?>" data-paypal-button="true">Sign up for PayPal</a>
        <script id="paypal-js" src="https://www.sandbox.paypal.com/webapps/merchantboarding/js/lib/lightbox/partner.js"></script>
        <?php
    }
}