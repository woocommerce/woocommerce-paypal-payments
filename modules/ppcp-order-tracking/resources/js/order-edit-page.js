document.addEventListener(
    'DOMContentLoaded',
    () => {
        const config = PayPalCommerceGatewayOrderTrackingInfo;
        if (!typeof (PayPalCommerceGatewayOrderTrackingInfo)) {
            console.error('trackign cannot be set.');
            return;
        }

        jQuery(document).on('click', '.submit_tracking_info', function () {
            const transactionId = document.querySelector('.ppcp-tracking-transaction_id');
            const trackingNumber = document.querySelector('.ppcp-tracking-tracking_number');
            const status = document.querySelector('.ppcp-tracking-status');
            const carrier = document.querySelector('.ppcp-tracking-carrier');
            const orderId = document.querySelector('.ppcp-order_id');
            const submitButton = document.querySelector('.submit_tracking_info');

            submitButton.setAttribute('disabled', 'disabled');
            fetch(config.ajax.tracking_info.endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    nonce: config.ajax.tracking_info.nonce,
                    transaction_id: transactionId ? transactionId.value : null,
                    tracking_number: trackingNumber ? trackingNumber.value : null,
                    status: status ? status.value : null,
                    carrier: carrier ? carrier.value : null,
                    order_id: orderId ? orderId.value : null,
                    action: submitButton ? submitButton.dataset.action : null,
                })
            }).then(function (res) {
                return res.json();
            }).then(function (data) {
                if (!data.success) {
                    jQuery( "<span class='error tracking-info-message'>" + data.data.message + "</span>" ).insertAfter(submitButton);
                    setTimeout(()=> jQuery('.tracking-info-message').remove(),3000);
                    submitButton.removeAttribute('disabled');
                    console.error(data);
                    throw Error(data.data.message);
                }

                jQuery( "<span class='success tracking-info-message'>" + data.data.message + "</span>" ).insertAfter(submitButton);
                setTimeout(()=> jQuery('.tracking-info-message').remove(),3000);

                submitButton.dataset.action = 'update';
                submitButton.textContent = 'update';
                submitButton.removeAttribute('disabled');
            });
        })
    },
);
