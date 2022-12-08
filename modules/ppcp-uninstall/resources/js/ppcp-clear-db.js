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
            }).then(function (res) {
                return res.json();
            }).then(function (data) {
                const resultMessage = document.querySelector(clearDbConfig.messageSelector);

                if (!data.success) {
                    clearDbConfig.failureMessage.insertAfter(clearButton);
                    setTimeout(()=> resultMessage.remove(),3000);
                    clearButton.removeAttribute('disabled');
                    console.error(data);
                    throw Error(data.data.message);
                }

                clearDbConfig.successMessage.insertAfter(clearButton);
                setTimeout(()=> resultMessage.remove(),3000);
                clearButton.removeAttribute('disabled');
            });
        })
    },
);
