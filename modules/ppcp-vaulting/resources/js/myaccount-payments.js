document.addEventListener(
    'DOMContentLoaded',
    () => {
        jQuery('.ppcp-delete-payment-button').click(async (event) => {
            event.preventDefault();
            jQuery(this).prop('disabled', true);
            const token = event.target.id;

            const response = await fetch(
                PayPalCommerceGatewayVaulting.delete.endpoint,
                {
                    method: 'POST',
                    headers: {
                        'content-type': 'application/json'
                    },
                    body: JSON.stringify(
                        {
                            nonce: PayPalCommerceGatewayVaulting.delete.nonce,
                            token,
                        }
                    )
                }
            );

            const reportError = error => {
                alert(error);
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
    });
