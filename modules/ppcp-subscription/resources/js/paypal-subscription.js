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

        document.getElementById('ppcp_unlink_sub_plan').addEventListener('click', (event)=>{
            event.preventDefault();
            fetch(PayPalCommerceGatewayPayPalSubscription.ajax.deactivate_plan.endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    nonce: PayPalCommerceGatewayPayPalSubscription.ajax.deactivate_plan.nonce,
                    plan_id: PayPalCommerceGatewayPayPalSubscription.ajax.deactivate_plan.plan_id,
                    product_id: PayPalCommerceGatewayPayPalSubscription.ajax.deactivate_plan.product_id
                })
            }).then(function (res) {
                return res.json();
            }).then(function (data) {
                console.log(data)
            });
        });
    });
