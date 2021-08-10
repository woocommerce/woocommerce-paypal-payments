;document.addEventListener(
    'DOMContentLoaded',
    () => {
        const payLaterMessagingCheckboxes = document.querySelectorAll(
            "#ppcp-message_enabled, #ppcp-message_cart_enabled, #ppcp-message_product_enabled"
        )

        const vaultingCheckboxes = document.querySelectorAll(
            "#ppcp-vault_enabled"
        )

        function atLeastOneChecked(checkboxesNodeList) {
            return Array.prototype.slice.call(checkboxesNodeList).filter(node => !node.disabled && node.checked).length > 0
        }

        function disableAll(nodeList){
            nodeList.forEach(node => node.setAttribute('disabled', 'true'))
        }

        function enableAll(nodeList){
            nodeList.forEach(node => node.removeAttribute('disabled'))
        }

        function updateCheckboxes() {
            atLeastOneChecked(payLaterMessagingCheckboxes) ? disableAll(vaultingCheckboxes) : enableAll(vaultingCheckboxes)
            atLeastOneChecked(vaultingCheckboxes) ? disableAll(payLaterMessagingCheckboxes) : enableAll(payLaterMessagingCheckboxes)

            if(typeof PayPalCommerceGatewaySettings === 'undefined' || PayPalCommerceGatewaySettings.vaulting_features_available !== '1' ) {
                disableAll(vaultingCheckboxes)
            }
        }

        updateCheckboxes()

        payLaterMessagingCheckboxes.forEach(node => node.addEventListener('change', updateCheckboxes))
        vaultingCheckboxes.forEach(node => node.addEventListener('change', updateCheckboxes));
    }
);
