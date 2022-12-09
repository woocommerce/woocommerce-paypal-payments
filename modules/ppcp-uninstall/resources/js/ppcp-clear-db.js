document.addEventListener(
    'DOMContentLoaded',
    () => {
        const config = PayPalCommerceGatewayClearDb;
        if (!typeof (config)) {
            return;
        }

        const clearDbConfig = config.clearDb;

        jQuery(document).on('click', clearDbConfig.button, function () {
            const isConfirmed = confirm(clearDbConfig.ConfirmationMessage);
            if (!isConfirmed) {
                return;
            }

            const clearButton = document.querySelector(clearDbConfig.button);

            clearButton.setAttribute('disabled', 'disabled');
            fetch(clearDbConfig.endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                body: JSON.stringify({
                    nonce: clearDbConfig.nonce,
                })
            }).then((res)=>{
                return res.json();
            }).then((data)=>{
                if (!data.success) {
                    jQuery(clearDbConfig.failureMessage).insertAfter(clearButton);
                    setTimeout(()=> jQuery(clearDbConfig.messageSelector).remove(),3000);
                    clearButton.removeAttribute('disabled');
                    throw Error(data.data.message);
                }

                jQuery(clearDbConfig.successMessage).insertAfter(clearButton);
                setTimeout(()=> jQuery(clearDbConfig.messageSelector).remove(),3000);
                clearButton.removeAttribute('disabled');
            });
        })
    },
);
