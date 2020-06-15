

const onboardingCallback = (authCode, sharedId) => {
    console.log(authCode);
    console.log(sharedId);
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
/*
const bootstrap = () => {
    if (!typeof (PayPalCommerceGatewayOnboarding)) {
        console.error('PayPal onboarding could not be configured.');
        return;
    }

    const link = document.createElement('a');
    link.innerText = PayPalCommerceGatewayOnboarding.link.text;

    Object.entries(PayPalCommerceGatewayOnboarding.link.attributes).forEach(
        (keyValue) => {
            link.setAttribute(keyValue[0], keyValue[1]);
        }
    );
    link.setAttribute('data-paypal-onboard-complete', 'onboardingCallback');

    const table = document.querySelector('.form-table');
    const wrapper = table.parentNode;
    //wrapper.insertBefore(link, table);

    const script = document.createElement('script');
    Object.entries(PayPalCommerceGatewayOnboarding.script.attributes).forEach(
        (keyValue) => {
            script.setAttribute(keyValue[0], keyValue[1]);
        }
    );
    wrapper.insertBefore(script, table);

};
bootstrap();

 */