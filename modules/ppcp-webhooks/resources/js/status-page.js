document.addEventListener(
    'DOMContentLoaded',
    () => {
        const resubscribeBtn = jQuery(PayPalCommerceGatewayWebhooksStatus.resubscribe.button);

        resubscribeBtn.click(async () => {
            resubscribeBtn.prop('disabled', true);

            const response = await fetch(
                PayPalCommerceGatewayWebhooksStatus.resubscribe.endpoint,
                {
                    method: 'POST',
                    headers: {
                        'content-type': 'application/json'
                    },
                    body: JSON.stringify(
                        {
                            nonce: PayPalCommerceGatewayWebhooksStatus.resubscribe.nonce,
                        }
                    )
                }
            );

            const reportError = error => {
                const msg = PayPalCommerceGatewayWebhooksStatus.resubscribe.failureMessage + ' ' + error;
                alert(msg);
            }

            if (!response.ok) {
                try {
                    const result = await response.json();
                    reportError(result.data);
                } catch (exc) {
                    console.error(exc);
                    reportError(response.status);
                }
            }

            window.location.reload();
        });
    }
);
