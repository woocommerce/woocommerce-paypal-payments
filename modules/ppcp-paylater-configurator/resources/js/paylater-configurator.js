document.addEventListener( 'DOMContentLoaded', () => {
    const form = document.querySelector('#mainform');
    const table = form.querySelector('.form-table');

    table.insertAdjacentHTML('afterend', '<div id="messaging-configurator"></div>');


    window.addEventListener('load', () => {
        const form = document.querySelector('#mainform');
        const messagingConfigurator = form.querySelector('#messaging-configurator');
        const publishButton = messagingConfigurator.querySelector('#configurator-publishButton');

        if (publishButton) {
            publishButton.style.display = 'none';
        }

        form.addEventListener('submit', () => {
            publishButton.click();
        });
    });


    merchantConfigurators.Messaging({
        config: PcpPayLaterConfigurator.config,
        merchantClientId: PcpPayLaterConfigurator.merchantClientId,
        partnerClientId: PcpPayLaterConfigurator.partnerClientId,
        partnerName: 'WooCommerce',
        bnCode: 'Woo_PPCP',
        placements: ['cart', 'checkout', 'product', 'category', 'homepage', 'custom_placement'],
        onSave: data => {
            fetch(PcpPayLaterConfigurator.ajax.save_config.endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    nonce: PcpPayLaterConfigurator.ajax.save_config.nonce,
                    config: data,
                }),
            });
        }
    })
} );
