;document.addEventListener(
    'DOMContentLoaded',
    () => {
        const disabledCheckboxes = document.querySelectorAll(
            '.ppcp-disabled-checkbox'
        )

        function disableAll(nodeList){
            nodeList.forEach(node => node.setAttribute('disabled', 'true'))
        }

        disableAll( disabledCheckboxes )

        if(PayPalCommerceGatewaySettings.is_subscriptions_plugin_active !== '1') {
            const subscriptionBehaviorWhenVaultFails = document.getElementById('field-subscription_behavior_when_vault_fails');
            if (subscriptionBehaviorWhenVaultFails) {
                subscriptionBehaviorWhenVaultFails.style.display = 'none'
            }
        }
    }
);
