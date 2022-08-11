import {PaymentMethods} from "../../../ppcp-button/resources/js/modules/Helper/CheckoutMethodState";

document.addEventListener(
    'DOMContentLoaded',
    () => {
        const config = PayPalCommerceGatewayOrderTrackingInfo;
        if (!typeof (PayPalCommerceGatewayOrderTrackingInfo)) {
            console.error('trackign cannot be set.');
            return;
        }

        const submitButton = jQuery('.submit_tracking_info');

        jQuery('.submit_tracking_info').click(function() {
            submitButton.prop( 'disabled', true );
            fetch(config.ajax.tracking_info.endpoint, {
                method: 'POST',
                body: JSON.stringify({
                    nonce: config.ajax.tracking_info.nonce,
                    transaction_id: jQuery('.ppcp-tracking-transaction_id').val(),
                    tracking_number: jQuery('.ppcp-tracking-tracking_number').val(),
                    status: jQuery('.ppcp-tracking-status').val(),
                    carrier: jQuery('.ppcp-tracking-carrier').val(),
                    order_id: jQuery('.ppcp-order_id').val(),
                    action: jQuery('.submit_tracking_info').data('action'),
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

                submitButton.html('update')
                submitButton.prop( 'disabled', false );
            });
        })
    },
);
