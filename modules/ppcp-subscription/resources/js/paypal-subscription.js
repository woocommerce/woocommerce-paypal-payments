document.addEventListener(
    'DOMContentLoaded',
    () => {
        if(PayPalCommerceGatewayPayPalSubscription.product_connected === 'yes') {
            const periodInterval = document.querySelector('#_subscription_period_interval');
            periodInterval.setAttribute('disabled', 'disabled');

            const subscriptionPeriod = document.querySelector('#_subscription_period');
            subscriptionPeriod.setAttribute('disabled', 'disabled');

            const subscriptionLength = document.querySelector('#_subscription_length');
            subscriptionLength.setAttribute('disabled', 'disabled');

            const subscriptionTrialLength = document.querySelector('#_subscription_trial_length');
            subscriptionTrialLength.setAttribute('disabled', 'disabled');

            const subscriptionTrialPeriod = document.querySelector('#_subscription_trial_period');
            subscriptionTrialPeriod.setAttribute('disabled', 'disabled');
        }
    });
