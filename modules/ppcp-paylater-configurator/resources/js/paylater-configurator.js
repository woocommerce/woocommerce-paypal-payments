document.addEventListener( 'DOMContentLoaded', () => {
    const form = document.querySelector('#mainform');
    form.insertAdjacentHTML('afterend', '<div id="messaging-configurator"></div>');

    merchantConfigurators.Messaging({
        config: PcpPayLaterConfigurator.config,
        merchantClientId: PcpPayLaterConfigurator.merchantClientId,
        partnerClientId: PcpPayLaterConfigurator.partnerClientId,
        partnerName: 'WooCommerce',
        bnCode: 'Woo_PPCP',
        placements: ['cart', 'checkout', 'product', 'category', 'homepage'],
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
