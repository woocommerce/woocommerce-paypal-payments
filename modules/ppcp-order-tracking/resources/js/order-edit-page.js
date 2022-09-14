import {PaymentMethods} from "../../../ppcp-button/resources/js/modules/Helper/CheckoutMethodState";

document.addEventListener(
    'DOMContentLoaded',
    () => {
        const config = PayPalCommerceGatewayOrderTrackingInfo;
        if (!typeof (PayPalCommerceGatewayOrderTrackingInfo)) {
            console.error('trackign cannot be set.');
            return;
        }

        const transactionId = document.querySelector('.ppcp-tracking-transaction_id');
        const trackingNumber = document.querySelector('.ppcp-tracking-tracking_number');
        const status = document.querySelector('.ppcp-tracking-status');
        const carrier = document.querySelector('.ppcp-tracking-carrier');
        const orderId = document.querySelector('.ppcp-order_id');
        const submitButton = document.querySelector('.submit_tracking_info');

        submitButton.addEventListener('click', function (event) {
            submitButton.setAttribute('disabled', 'disabled');
            fetch(config.ajax.tracking_info.endpoint, {
                method: 'POST',
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
