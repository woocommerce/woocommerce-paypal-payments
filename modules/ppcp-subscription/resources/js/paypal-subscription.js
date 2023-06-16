document.addEventListener(
    'DOMContentLoaded',
    () => {
        if(PayPalCommerceGatewayPayPalSubscription.product_connected === 'yes') {
            const periodInterval = document.querySelector('#_subscription_period_interval');
            periodInterval.setAttribute('disabled', 'disabled');

            const subscriptionPeriod = document.querySelector('#_subscription_period');
            subscriptionPeriod.setAttribute('disabled', 'disabled');

            const subscriptionLength = document.querySelector('._subscription_length_field');
            subscriptionLength.style.display = 'none';

            const subscriptionTrial = document.querySelector('._subscription_trial_length_field');
            subscriptionTrial.style.display = 'none';
        }

        const unlinkBtn = document.getElementById('ppcp_unlink_sub_plan');
        unlinkBtn?.addEventListener('click', (event)=>{
            event.preventDefault();
            unlinkBtn.disabled = true;
            const spinner = document.getElementById('spinner-unlink-plan');
            spinner.style.display = 'inline-block';

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
                if (!data.success) {
                    unlinkBtn.disabled = false;
                    spinner.style.display = 'none';
                    console.error(data);
                    throw Error(data.data.message);
                }

                const enableSubscription = document.getElementById('ppcp-enable-subscription');
                const product = document.getElementById('pcpp-product');
                const plan = document.getElementById('pcpp-plan');
                enableSubscription.style.display = 'none';
                product.style.display = 'none';
                plan.style.display = 'none';

                const enable_subscription_product = document.getElementById('ppcp_enable_subscription_product');
                enable_subscription_product.disabled = true;

                const planUnlinked = document.getElementById('pcpp-plan-unlinked');
                planUnlinked.style.display = 'block';

                setTimeout(() => {
                    location.reload();
                }, 1000)
            });
        });
    });
