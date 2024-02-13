document.addEventListener( 'DOMContentLoaded', () => {
    const form = document.querySelector('#mainform');
    const table = form.querySelector('.form-table');
    const saveChangesButton = form.querySelector('.woocommerce-save-button');
    const publishButtonClassName = PcpPayLaterConfigurator.publishButtonClassName;

    table.insertAdjacentHTML('afterend', '<div id="messaging-configurator"></div>');


    saveChangesButton.addEventListener('click', () => {
        form.querySelector('.' + publishButtonClassName).click();

        // Delay the page refresh by a few milliseconds to ensure changes take effect
        setTimeout(() => {
            location.reload();
        }, 1000);
    });

    merchantConfigurators.Messaging({
        config: PcpPayLaterConfigurator.config,
        merchantClientId: PcpPayLaterConfigurator.merchantClientId,
        partnerClientId: PcpPayLaterConfigurator.partnerClientId,
        partnerName: 'WooCommerce',
        bnCode: 'Woo_PPCP',
        placements: ['cart', 'checkout', 'product', 'category', 'homepage', 'custom_placement'],
        styleOverrides: {
            button: publishButtonClassName,
            header: PcpPayLaterConfigurator.headerClassName,
            subheader: PcpPayLaterConfigurator.subheaderClassName
        },
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
